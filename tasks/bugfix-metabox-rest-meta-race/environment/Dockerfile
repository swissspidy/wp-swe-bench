ARG WPSB_BASE_IMAGE=ghcr.io/swissspidy/wp-swe-bench-base:0.1.0
FROM ${WPSB_BASE_IMAGE}

ENV WPSB_REPO=/wordpress/wp-content/plugins/acme-product-fields

COPY app/ /wordpress/wp-content/plugins/acme-product-fields/
COPY blueprint.json seed.sh /opt/wpsb/task/
COPY seed/ /opt/wpsb/task/seed/

RUN wpsb-provision

WORKDIR /wordpress/wp-content/plugins/acme-product-fields
