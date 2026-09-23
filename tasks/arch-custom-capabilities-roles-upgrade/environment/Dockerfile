ARG WPSB_BASE_IMAGE=ghcr.io/swissspidy/wp-swe-bench-base:0.1.0
FROM ${WPSB_BASE_IMAGE}

ENV WPSB_REPO=/wordpress/wp-content/plugins/acme-newsroom

COPY app/ /wordpress/wp-content/plugins/acme-newsroom/
COPY blueprint.json seed.sh /opt/wpsb/task/

RUN wpsb-provision

WORKDIR /wordpress/wp-content/plugins/acme-newsroom
