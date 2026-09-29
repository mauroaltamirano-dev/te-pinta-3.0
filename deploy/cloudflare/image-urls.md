# Same-origin public product images

Public catalog and featured-product image URLs are derived only from validated
`products/<basename>.<jpg|jpeg|png|webp>` paths. They are returned as
`/storage/products/...`, rather than using `APP_URL`, so a public storefront and
the private Tailscale origin each load the same product image from their own host.

Missing, malformed, traversal-like, encoded, query-bearing, fragment-bearing, or
unsupported paths return `null`. This matches the public gateway's product-media
intent while deliberately using a narrower canonical upload-safe policy than its
filename matcher. It does not change administrative serializers, the public storage disk,
or private-origin `APP_URL`/WebAuthn configuration.

Run the focused checks with:

```bash
./vendor/bin/sail test tests/Feature/Products/PublicProductImageUrlTest.php tests/Feature/Marketing/FeaturedProductImageUrlTest.php
```

This source fix is not a deployment. The currently running operational image must
be replaced only through a separately reviewed, narrowly derived immutable image;
do not rebuild the shared dirty checkout or alter the private runtime for this
change.
