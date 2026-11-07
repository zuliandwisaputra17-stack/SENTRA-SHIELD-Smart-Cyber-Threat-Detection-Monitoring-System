# Target satuan (kodim-a / kodim-b): portal dummy PHP + sshd + SENTRA Agent.
# Build context: root repo (agar agent/ ikut).
ARG BASE_IMAGE=ubuntu:24.04
FROM ${BASE_IMAGE}
ENV DEBIAN_FRONTEND=noninteractive
RUN apt-get update \
    && apt-get install -y --no-install-recommends nginx php8.3-cli php8.3-fpm php8.3-sqlite3 openssh-server \
       nftables python3 supervisor ca-certificates iproute2 procps \
    && rm -rf /var/lib/apt/lists/* /etc/nginx/sites-enabled/default /var/www/html/*
COPY agent/sentra_agent.py /opt/sentra-agent/sentra_agent.py
COPY lab/common/agent-run.sh /usr/local/bin/sentra-agent-run
COPY lab/target/portal/ /var/www/html/
COPY lab/target/portal-seed.php /opt/portal-seed.php
COPY lab/target/nginx.conf /etc/nginx/conf.d/portal.conf
COPY lab/target/sshd.conf /etc/ssh/sshd_config.d/lab.conf
COPY lab/target/supervisord.conf /etc/supervisor/conf.d/target.conf
COPY lab/target/entrypoint.sh /usr/local/bin/target-entrypoint
# Izin dinormalkan: build context dari Windows/OneDrive bisa membawa atribut read-only.
RUN chmod +x /usr/local/bin/sentra-agent-run /usr/local/bin/target-entrypoint \
    && chmod -R u+rwX,go+rX,go-w /var/www/html /opt/sentra-agent /opt/portal-seed.php
EXPOSE 22 80
ENTRYPOINT ["/usr/local/bin/target-entrypoint"]
