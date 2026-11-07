# PLC simulasi (Modbus TCP) + SENTRA Agent sensor OT pasif.
# Build context: root repo.
ARG BASE_IMAGE=ubuntu:24.04
FROM ${BASE_IMAGE}
ENV DEBIAN_FRONTEND=noninteractive
RUN apt-get update \
    && apt-get install -y --no-install-recommends python3 python3-pymodbus nftables supervisor ca-certificates iproute2 procps \
    && rm -rf /var/lib/apt/lists/*
COPY agent/sentra_agent.py /opt/sentra-agent/sentra_agent.py
COPY lab/common/agent-run.sh /usr/local/bin/sentra-agent-run
COPY lab/plc-sim/plc_server.py /opt/plc/plc_server.py
COPY lab/plc-sim/supervisord.conf /etc/supervisor/conf.d/plc.conf
# Izin dinormalkan: build context dari Windows/OneDrive bisa membawa atribut read-only.
RUN chmod +x /usr/local/bin/sentra-agent-run \
    && chmod -R u+rwX,go+rX,go-w /opt/sentra-agent /opt/plc
EXPOSE 502
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
