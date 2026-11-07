# Mesin penyerang lab. Hanya tersambung ke net-wan dengan satu IP tetap.
ARG BASE_IMAGE=ubuntu:24.04
FROM ${BASE_IMAGE}
ENV DEBIAN_FRONTEND=noninteractive
RUN apt-get update \
    && apt-get install -y --no-install-recommends nmap hydra sqlmap curl netcat-openbsd python3 python3-pymodbus \
       iputils-ping ca-certificates \
    && rm -rf /var/lib/apt/lists/*
COPY wordlists/ /opt/serangan/wordlists/
COPY skenario/ /opt/serangan/skenario/
# Izin dinormalkan: build context dari Windows/OneDrive bisa membawa atribut read-only.
RUN chmod -R u+rwX,go+rX,go-w /opt/serangan \
    && chmod +x /opt/serangan/skenario/*
WORKDIR /opt/serangan
CMD ["sleep", "infinity"]
