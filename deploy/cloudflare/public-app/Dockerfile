FROM sha256:e2c78c01075a5a774cb1164a8bc93736a68994dc3b6991f959df603aada2a519

LABEL org.opencontainers.image.base.digest="sha256:e2c78c01075a5a774cb1164a8bc93736a68994dc3b6991f959df603aada2a519"

WORKDIR /var/www/html

ARG PRODUCT_RESOURCE_SHA256
ARG FEATURED_VIEW_SHA256
ARG HELPER_SHA256

COPY deploy/cloudflare/public-app/apply-image-overlay.php /usr/local/bin/apply-image-overlay.php
COPY app/Modules/Products/Support/PublicProductImageUrl.php /tmp/PublicProductImageUrl.php

RUN php /usr/local/bin/apply-image-overlay.php \
    "$PRODUCT_RESOURCE_SHA256" \
    "$FEATURED_VIEW_SHA256" \
    "$HELPER_SHA256" \
    /tmp/PublicProductImageUrl.php \
    && rm /usr/local/bin/apply-image-overlay.php /tmp/PublicProductImageUrl.php
