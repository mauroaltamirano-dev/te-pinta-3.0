<?php

namespace App\Core\Branding;

use App\Core\Tenancy\RestaurantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Name and storefront copy of the restaurant in context, so one codebase serves several installations.
 *
 * @phpstan-type Identity array{name: string, eyebrow: ?string, headline: ?string, headline_highlight: ?string, intro: ?string, home_title: ?string, highlights: list<string>, footer_text: ?string, location: ?string, coming_soon_text: ?string, empty_cart_text: ?string}
 */
final class RestaurantIdentity
{
    /** Public field => restaurants column. */
    private const COLUMNS = [
        'eyebrow' => 'public_eyebrow',
        'headline' => 'public_headline',
        'headline_highlight' => 'public_headline_highlight',
        'intro' => 'public_intro',
        'home_title' => 'public_home_title',
        'footer_text' => 'public_footer_text',
        'location' => 'public_location',
        'coming_soon_text' => 'public_coming_soon_text',
        'empty_cart_text' => 'public_empty_cart_text',
    ];

    public function __construct(
        private readonly RestaurantContext $context,
        private readonly Request $request,
    ) {}

    /** @return Identity */
    public function current(): array
    {
        $key = 'branding.identity.'.$this->context->id();
        $cached = $this->request->attributes->get($key);

        if (is_array($cached)) {
            /** @var Identity $cached */
            return $cached;
        }

        $row = DB::table('restaurants')
            ->where('id', $this->context->id())
            ->first(['name', 'public_highlights', ...array_values(self::COLUMNS)]);

        $text = function (string $field) use ($row): ?string {
            $value = $row?->{self::COLUMNS[$field]};

            return is_string($value) && $value !== '' ? $value : null;
        };
        $highlights = is_string($row?->public_highlights) ? json_decode($row->public_highlights, true) : [];

        $identity = [
            'name' => is_string($row?->name) ? $row->name : '',
            'eyebrow' => $text('eyebrow'),
            'headline' => $text('headline'),
            'headline_highlight' => $text('headline_highlight'),
            'intro' => $text('intro'),
            'home_title' => $text('home_title'),
            'highlights' => array_values(array_filter(
                is_array($highlights) ? $highlights : [],
                fn (mixed $highlight): bool => is_string($highlight) && $highlight !== '',
            )),
            'footer_text' => $text('footer_text'),
            'location' => $text('location'),
            'coming_soon_text' => $text('coming_soon_text'),
            'empty_cart_text' => $text('empty_cart_text'),
        ];
        $this->request->attributes->set($key, $identity);

        return $identity;
    }

    public function name(): string
    {
        return $this->current()['name'];
    }

    /** @param Identity $identity */
    public function update(array $identity): void
    {
        $values = ['name' => $identity['name']];

        foreach (self::COLUMNS as $field => $column) {
            $values[$column] = $identity[$field];
        }

        $values['public_highlights'] = json_encode($identity['highlights'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $values['updated_at'] = now();

        DB::table('restaurants')->where('id', $this->context->id())->update($values);
        $this->request->attributes->remove('branding.identity.'.$this->context->id());
    }
}
