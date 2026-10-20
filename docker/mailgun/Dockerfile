FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl jq \
    && rm -rf /var/lib/apt/lists/* \
    && useradd --create-home --shell /usr/sbin/nologin mailgun

WORKDIR /opt/mailgun

COPY entrypoint.sh sync-messages.sh /opt/mailgun/

RUN chmod 755 /opt/mailgun/entrypoint.sh /opt/mailgun/sync-messages.sh

USER mailgun

ENTRYPOINT ["/opt/mailgun/entrypoint.sh"]
