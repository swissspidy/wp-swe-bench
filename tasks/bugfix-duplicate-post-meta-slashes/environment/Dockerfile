ARG WPSB_BASE_IMAGE=ghcr.io/swissspidy/wp-swe-bench-base:0.1.0
FROM ${WPSB_BASE_IMAGE}

# The repository is the Yoast Duplicate Post plugin (4.7 from wordpress.org, installed by the
# blueprint) with the site's in-house changes applied by seed.sh.
ENV WPSB_REPO=/wordpress/wp-content/plugins/duplicate-post

COPY blueprint.json seed.sh /opt/wpsb/task/
COPY seed/ /opt/wpsb/task/seed/

RUN wpsb-provision

WORKDIR /wordpress/wp-content/plugins/duplicate-post
