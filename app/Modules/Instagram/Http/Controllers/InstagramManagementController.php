<?php

namespace App\Modules\Instagram\Http\Controllers;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Instagram\Actions\ArchiveInstagramStoryPublication;
use App\Modules\Instagram\Actions\ArchiveOldInstagramStoryPublications;
use App\Modules\Instagram\Actions\CancelInstagramStoryPublication;
use App\Modules\Instagram\Actions\RetryInstagramStoryPublication;
use App\Modules\Instagram\Actions\StoreInstagramStoryAssetFile;
use App\Modules\Instagram\Enums\InstagramStoryPublicationStatus;
use App\Modules\Instagram\Http\Requests\StoreInstagramStoryAssetRequest;
use App\Modules\Instagram\Http\Requests\StoreInstagramStoryScheduleRequest;
use App\Modules\Instagram\Http\Requests\UpdateInstagramStoryAssetRequest;
use App\Modules\Instagram\Http\Requests\UpdateInstagramStoryScheduleRequest;
use App\Modules\Instagram\Models\InstagramAutomationSetting;
use App\Modules\Instagram\Models\InstagramStoryAsset;
use App\Modules\Instagram\Models\InstagramStoryPublication;
use App\Modules\Instagram\Models\InstagramStorySchedule;
use App\Modules\Instagram\Support\InstagramStoryMediaUrl;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class InstagramManagementController
{
    public function index(Request $request, InstagramStoryMediaUrl $mediaUrl): Response
    {
        abort_unless($request->user()?->can(Permission::MarketingManage->value) === true, 403);
        $validated = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(['all', ...array_column(InstagramStoryPublicationStatus::cases(), 'value')])],
            'period' => ['sometimes', 'string', Rule::in(['today', '7_days', '30_days', 'all'])],
            'search' => ['sometimes', 'nullable', 'string', 'max:160'],
            'show_archived' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $filters = [
            'status' => $validated['status'] ?? 'all',
            'period' => $validated['period'] ?? '30_days',
            'search' => trim((string) ($validated['search'] ?? '')),
            'show_archived' => $request->boolean('show_archived'),
        ];
        $timezone = (string) config('instagram.timezone');
        $localNow = CarbonImmutable::now($timezone);
        $todayStart = $localNow->startOfDay()->utc();
        $todayEnd = $localNow->addDay()->startOfDay()->utc();

        $assets = InstagramStoryAsset::query()
            ->withCount(['schedules', 'publications', 'activeSchedules'])
            ->with(['activeSchedules' => fn ($query) => $query->select(['id', 'asset_id', 'name'])->orderBy('name')])
            ->orderBy('name')
            ->get();
        $publicationQuery = InstagramStoryPublication::query()->with([
            'asset' => fn ($query) => $query
                ->withCount(['schedules', 'publications', 'activeSchedules'])
                ->with(['activeSchedules' => fn ($schedules) => $schedules->select(['id', 'asset_id', 'name'])->orderBy('name')]),
            'schedule',
        ]);
        if (! $filters['show_archived']) {
            $publicationQuery->whereNull('archived_at');
        }
        if ($filters['status'] !== 'all') {
            $publicationQuery->where('status', $filters['status']);
        }
        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $publicationQuery->where(function (Builder $query) use ($search): void {
                $query
                    ->whereHas('asset', fn (Builder $asset) => $asset->where('name', 'ilike', "%{$search}%"))
                    ->orWhereHas('schedule', fn (Builder $schedule) => $schedule->where('name', 'ilike', "%{$search}%"));
            });
        }
        $periodStart = match ($filters['period']) {
            'today' => $localNow->startOfDay(),
            '7_days' => $localNow->startOfDay()->subDays(6),
            '30_days' => $localNow->startOfDay()->subDays(29),
            default => null,
        };
        if ($periodStart !== null) {
            $publicationQuery->where('scheduled_for', '>=', $periodStart->utc());
        }
        $publications = $publicationQuery->orderByDesc('scheduled_for')->orderByDesc('id')->paginate(20)->withQueryString();

        $lastPublishedAt = InstagramStoryPublication::query()
            ->where('status', InstagramStoryPublicationStatus::Published->value)
            ->max('published_at');
        $lastAutomaticPublishedAt = InstagramStoryPublication::query()
            ->whereNotNull('schedule_id')
            ->where('status', InstagramStoryPublicationStatus::Published->value)
            ->max('published_at');

        return Inertia::render('admin/instagram/index', [
            'automation' => [
                'enabled' => InstagramAutomationSetting::current()->enabled,
                'available' => (bool) config('instagram.automation_enabled'),
                'effective_enabled' => (bool) config('instagram.automation_enabled') && InstagramAutomationSetting::current()->enabled,
                'timezone' => $timezone,
                'last_automatic_published_at' => $lastAutomaticPublishedAt ? CarbonImmutable::parse($lastAutomaticPublishedAt)->toIso8601String() : null,
            ],
            'assets' => $assets->map(fn (InstagramStoryAsset $asset): array => $this->assetData($asset, $mediaUrl))->all(),
            'schedules' => InstagramStorySchedule::query()
                ->with(['asset' => fn ($query) => $query
                    ->withCount(['schedules', 'publications', 'activeSchedules'])
                    ->with(['activeSchedules' => fn ($schedules) => $schedules->select(['id', 'asset_id', 'name'])->orderBy('name')])])
                ->orderBy('time')->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (InstagramStorySchedule $schedule): array => [
                    'id' => $schedule->id,
                    'name' => $schedule->name,
                    'time' => substr((string) $schedule->time, 0, 5),
                    'days_of_week' => $schedule->days_of_week,
                    'sort_order' => $schedule->sort_order,
                    'asset_id' => $schedule->asset_id,
                    'is_active' => $schedule->is_active,
                    'next_run_at' => $this->nextRunAt($schedule, $localNow),
                    'asset' => $schedule->asset ? $this->assetData($schedule->asset, $mediaUrl) : null,
                ])->all(),
            'publications' => $publications->getCollection()
                ->map(fn (InstagramStoryPublication $publication): array => $this->publicationData($publication, $mediaUrl))->all(),
            'publication_filters' => $filters,
            'publication_pagination' => [
                'current_page' => $publications->currentPage(),
                'last_page' => $publications->lastPage(),
                'per_page' => $publications->perPage(),
                'total' => $publications->total(),
                'from' => $publications->firstItem(),
                'to' => $publications->lastItem(),
            ],
            'pending_publications_count' => InstagramStoryPublication::query()->where('status', InstagramStoryPublicationStatus::Pending->value)->count(),
            'operational_summary' => [
                'pending' => InstagramStoryPublication::query()->where('status', InstagramStoryPublicationStatus::Pending->value)->count(),
                'processing' => InstagramStoryPublication::query()->where('status', InstagramStoryPublicationStatus::Processing->value)->count(),
                'errors_today' => InstagramStoryPublication::query()
                    ->where('status', InstagramStoryPublicationStatus::Failed->value)
                    ->where('failed_at', '>=', $todayStart)->where('failed_at', '<', $todayEnd)->count(),
                'last_published_at' => $lastPublishedAt ? CarbonImmutable::parse($lastPublishedAt)->toIso8601String() : null,
                'last_automatic_published_at' => $lastAutomaticPublishedAt ? CarbonImmutable::parse($lastAutomaticPublishedAt)->toIso8601String() : null,
            ],
        ]);
    }

    public function updateAutomation(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can(Permission::MarketingManage->value) === true, 403);
        $validated = $request->validate(['enabled' => ['required', 'boolean']]);
        InstagramAutomationSetting::current()->update(['enabled' => $validated['enabled']]);

        return back()->with('success', 'Automatización de Instagram actualizada.');
    }

    public function storeAsset(StoreInstagramStoryAssetRequest $request, StoreInstagramStoryAssetFile $storeFile): RedirectResponse
    {
        $file = $storeFile->execute($request->file('image'));
        InstagramStoryAsset::query()->create([...$request->safe()->only(['name', 'category', 'is_active']), ...$file, 'is_active' => $request->boolean('is_active', true)]);

        return back()->with('success', 'Imagen de historia subida.');
    }

    public function updateAsset(UpdateInstagramStoryAssetRequest $request, InstagramStoryAsset $asset, StoreInstagramStoryAssetFile $storeFile): RedirectResponse
    {
        $attributes = $request->safe()->only(['name', 'category', 'is_active']);
        $oldPath = DB::transaction(function () use ($request, $asset, $storeFile, &$attributes): ?string {
            $locked = InstagramStoryAsset::query()->lockForUpdate()->findOrFail($asset->id);
            $activeScheduleNames = $locked->activeSchedules()->orderBy('name')->pluck('name');
            if (! (bool) ($attributes['is_active'] ?? $locked->is_active) && $activeScheduleNames->isNotEmpty()) {
                throw ValidationException::withMessages(['is_active' => $this->assetReferenceMessage($activeScheduleNames->all())]);
            }
            $oldPath = null;
            if ($request->hasFile('image')) {
                if ($locked->publications()->exists() || $activeScheduleNames->isNotEmpty()) {
                    throw ValidationException::withMessages(['image' => 'No se puede reemplazar una imagen usada por una publicación o programación activa. Subí una imagen nueva.']);
                }
                $oldPath = $locked->file_path;
                $attributes = [...$attributes, ...$storeFile->execute($request->file('image'))];
            }
            $locked->update($attributes);

            return $oldPath;
        });
        if ($oldPath !== null) {
            Storage::disk((string) config('instagram.media_disk'))->delete($oldPath);
        }

        return back()->with('success', 'Imagen de historia actualizada.');
    }

    public function destroyAsset(Request $request, InstagramStoryAsset $asset): RedirectResponse
    {
        abort_unless($request->user()?->can(Permission::MarketingManage->value) === true, 403);
        $path = DB::transaction(function () use ($asset): string {
            $locked = InstagramStoryAsset::query()->lockForUpdate()->findOrFail($asset->id);
            $scheduleNames = $locked->schedules()->orderBy('name')->pluck('name');
            if ($scheduleNames->isNotEmpty()) {
                throw ValidationException::withMessages(['asset' => $this->assetReferenceMessage($scheduleNames->all())]);
            }
            if ($locked->publications()->exists()) {
                throw ValidationException::withMessages(['asset' => 'Esta historia tiene publicaciones asociadas y no se puede eliminar. Desactivala.']);
            }
            $path = $locked->file_path;
            $locked->delete();

            return $path;
        });
        Storage::disk((string) config('instagram.media_disk'))->delete($path);

        return back()->with('success', 'Imagen de historia eliminada.');
    }

    public function storeSchedule(StoreInstagramStoryScheduleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['sort_order'] ??= 1;
        DB::transaction(function () use ($data): void {
            $this->ensureActiveScheduleAsset($data);
            InstagramStorySchedule::query()->create($data);
        });

        return back()->with('success', 'Programación creada.');
    }

    public function updateSchedule(UpdateInstagramStoryScheduleRequest $request, InstagramStorySchedule $schedule): RedirectResponse
    {
        $data = $request->validated();
        $data['sort_order'] ??= $schedule->sort_order;
        DB::transaction(function () use ($data, $schedule): void {
            $locked = InstagramStorySchedule::query()->lockForUpdate()->findOrFail($schedule->id);
            $this->ensureActiveScheduleAsset($data);
            $locked->update($data);
        });

        return back()->with('success', 'Programación actualizada.');
    }

    public function destroySchedule(Request $request, InstagramStorySchedule $schedule): RedirectResponse
    {
        abort_unless($request->user()?->can(Permission::MarketingManage->value) === true, 403);
        DB::transaction(function () use ($schedule): void {
            $locked = InstagramStorySchedule::query()->lockForUpdate()->findOrFail($schedule->id);
            if ($locked->publications()->exists()) {
                throw ValidationException::withMessages(['schedule' => 'No se puede eliminar una programación con publicaciones asociadas. Desactivala.']);
            }
            $locked->delete();
        });

        return back()->with('success', 'Programación eliminada.');
    }

    public function storePublication(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can(Permission::MarketingManage->value) === true, 403);
        $data = $request->validate(['asset_id' => ['required', 'integer', 'exists:instagram_story_assets,id']]);
        DB::transaction(function () use ($data): void {
            $asset = InstagramStoryAsset::query()->lockForUpdate()->whereKey($data['asset_id'])->where('is_active', true)->first();
            if ($asset === null) {
                throw ValidationException::withMessages(['asset_id' => 'Sólo se puede agregar a la cola una historia activa.']);
            }
            InstagramStoryPublication::query()->create([
                'asset_id' => $asset->id,
                'scheduled_for' => now(),
                'queue_order' => 0,
                'status' => InstagramStoryPublicationStatus::Pending,
            ]);
        });

        return back()->with('success', 'Historia enviada a la cola de publicación.');
    }

    public function archivePublication(Request $request, InstagramStoryPublication $publication, ArchiveInstagramStoryPublication $archive): RedirectResponse
    {
        abort_unless($request->user()?->can(Permission::MarketingManage->value) === true, 403);
        $archive->execute($publication);

        return back()->with('success', 'Publicación archivada.');
    }

    public function archiveOldPublications(Request $request, ArchiveOldInstagramStoryPublications $archive): RedirectResponse
    {
        abort_unless($request->user()?->can(Permission::MarketingManage->value) === true, 403);
        $validated = $request->validate(['days' => ['required', 'integer', Rule::in([30])]]);
        $count = $archive->execute($validated['days']);

        return back()->with('success', "{$count} publication(s) archived.");
    }

    public function retryPublication(Request $request, InstagramStoryPublication $publication, RetryInstagramStoryPublication $retry): RedirectResponse
    {
        abort_unless($request->user()?->can(Permission::MarketingManage->value) === true, 403);
        $retry->execute($publication);

        return back()->with('success', 'Publicación devuelta a la cola.');
    }

    public function cancelPublication(Request $request, InstagramStoryPublication $publication, CancelInstagramStoryPublication $cancel): RedirectResponse
    {
        abort_unless($request->user()?->can(Permission::MarketingManage->value) === true, 403);
        $cancel->execute($publication);

        return back()->with('success', 'Publicación cancelada.');
    }

    /** @param array<string, mixed> $data */
    private function ensureActiveScheduleAsset(array $data): void
    {
        if ($data['is_active'] && $data['asset_id'] === null) {
            throw ValidationException::withMessages(['asset_id' => 'Una programación activa requiere una imagen.']);
        }
        if ($data['is_active']) {
            $asset = InstagramStoryAsset::query()->lockForUpdate()->whereKey($data['asset_id'])->where('is_active', true)->first(['id']);
            if ($asset === null) {
                throw ValidationException::withMessages(['asset_id' => 'Una programación activa requiere una imagen de historia activa.']);
            }
        }
    }

    /** @return array<string, mixed> */
    private function assetData(InstagramStoryAsset $asset, InstagramStoryMediaUrl $mediaUrl): array
    {
        $schedulesCount = $asset->schedules_count ?? $asset->schedules()->count();
        $publicationsCount = $asset->publications_count ?? $asset->publications()->count();
        $activeSchedules = $asset->relationLoaded('activeSchedules')
            ? $asset->activeSchedules
            : $asset->activeSchedules()->orderBy('name')->get(['id', 'asset_id', 'name']);
        $inUse = $schedulesCount > 0 || $publicationsCount > 0;

        return [
            'id' => $asset->id, 'name' => $asset->name, 'category' => $asset->category,
            'mime_type' => $asset->mime_type, 'width' => $asset->width, 'height' => $asset->height,
            'is_active' => $asset->is_active, 'preview_url' => $mediaUrl->temporary($asset, 30),
            'schedules_count' => $schedulesCount, 'publications_count' => $publicationsCount,
            'active_schedules_count' => $asset->active_schedules_count ?? $activeSchedules->count(),
            'active_schedule_references' => $activeSchedules->map(fn (InstagramStorySchedule $schedule): array => ['id' => $schedule->id, 'name' => $schedule->name])->values()->all(),
            'in_use' => $inUse, 'can_delete' => ! $inUse,
        ];
    }

    /** @return array<string, mixed> */
    private function publicationData(InstagramStoryPublication $publication, InstagramStoryMediaUrl $mediaUrl): array
    {
        $canRetry = $publication->status === InstagramStoryPublicationStatus::Failed
            && $publication->retryable && ! $publication->requires_manual_review
            && $publication->meta_media_id === null
            && $publication->attempts < max(1, (int) config('instagram.max_attempts'));
        $canCancel = in_array($publication->status, [InstagramStoryPublicationStatus::Pending, InstagramStoryPublicationStatus::Failed], true)
            && ! $publication->requires_manual_review && $publication->meta_media_id === null;
        $canArchive = in_array($publication->status, [InstagramStoryPublicationStatus::Published, InstagramStoryPublicationStatus::Failed, InstagramStoryPublicationStatus::Cancelled], true)
            && $publication->archived_at === null;

        return [
            'id' => $publication->id,
            'name' => $publication->asset->name,
            'type' => $publication->schedule_id === null ? 'manual' : 'automatic',
            'scheduled_for' => $publication->scheduled_for->toIso8601String(),
            'processing_started_at' => $publication->processing_started_at?->toIso8601String(),
            'published_at' => $publication->published_at?->toIso8601String(),
            'failed_at' => $publication->failed_at?->toIso8601String(),
            'cancelled_at' => $publication->cancelled_at?->toIso8601String(),
            'archived_at' => $publication->archived_at?->toIso8601String(),
            'status' => $publication->status->value,
            'attempts' => $publication->attempts,
            'error_message' => $publication->error_message,
            'failure_code' => $publication->failure_code,
            'retryable' => $publication->retryable,
            'requires_manual_review' => $publication->requires_manual_review,
            'meta_container_id' => $publication->meta_container_id,
            'meta_media_id' => $publication->meta_media_id,
            'can_retry' => $canRetry, 'can_cancel' => $canCancel, 'can_archive' => $canArchive,
            'asset' => $this->assetData($publication->asset, $mediaUrl),
            'schedule' => $publication->schedule ? ['id' => $publication->schedule->id, 'name' => $publication->schedule->name] : null,
        ];
    }

    private function nextRunAt(InstagramStorySchedule $schedule, CarbonImmutable $localNow): ?string
    {
        if (! $schedule->is_active || $schedule->asset === null || ! $schedule->asset->is_active) {
            return null;
        }
        $days = array_map('intval', $schedule->days_of_week);
        for ($offset = 0; $offset <= 7; $offset++) {
            $date = $localNow->startOfDay()->addDays($offset);
            if (! in_array($date->dayOfWeekIso, $days, true)) {
                continue;
            }
            $candidate = CarbonImmutable::parse($date->toDateString().' '.$schedule->time, $localNow->timezone)->startOfMinute();
            if ($candidate->greaterThanOrEqualTo($localNow->startOfMinute())) {
                return $candidate->toIso8601String();
            }
        }

        return null;
    }

    /** @param array<int, string> $scheduleNames */
    private function assetReferenceMessage(array $scheduleNames): string
    {
        $count = count($scheduleNames);
        $names = implode(', ', $scheduleNames);

        return "Esta historia se usa en {$count} programación(es): {$names}. Primero reasignalas o desactivalas.";
    }
}
