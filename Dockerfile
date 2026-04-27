FROM php:8.2-fpm-bookworm

ENV DEBIAN_FRONTEND=noninteractive \
    PYTHONUNBUFFERED=1 \
    PIP_NO_CACHE_DIR=1 \
    PATH="/opt/venv/bin:${PATH}"

RUN apt-get update && apt-get install -y --no-install-recommends \
        build-essential \
        ca-certificates \
        default-libmysqlclient-dev \
        gettext-base \
        libcurl4-openssl-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        nginx \
        python3 \
        python3-dev \
        python3-pip \
        python3-venv \
        supervisor \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" curl fileinfo gd mbstring pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY python_api/requirements.render.txt /tmp/requirements.render.txt
# Install Python dependencies into an isolated virtual environment to avoid
# Debian's externally-managed system Python restriction.
RUN python3 -m venv /opt/venv \
    && /opt/venv/bin/pip install --no-cache-dir --upgrade pip setuptools wheel \
    && /opt/venv/bin/pip install --no-cache-dir --prefer-binary -r /tmp/requirements.render.txt

COPY . /var/www/html
COPY deploy/nginx.conf.template /etc/nginx/templates/agrico.conf.template
COPY deploy/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY deploy/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 10000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
