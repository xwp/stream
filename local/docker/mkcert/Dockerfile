# Build a small mkcert image used by the `mkcert` service in
# docker-compose.yml to generate a locally-trusted TLS cert + key for the
# Stream development environment. Adopted from the XWP VIP site template:
# https://github.com/xwp/vip-site-template/blob/main/local/docker/mkcert/Dockerfile
#
# The container itself doesn't run a long-lived process. docker-compose
# invokes it once with `mkcert -cert-file ... -key-file ...` and the files
# land in the bind-mounted `local/certs/` directory, which the WordPress
# container reads from to serve HTTPS via the stream-ssl Apache vhost.

FROM golang:1.22-alpine

# Set the mkcert version to build. Bump as upstream releases.
ENV MKCERT_VERSION="v1.4.4"

RUN apk add --no-cache git

RUN git clone https://github.com/FiloSottile/mkcert /go/mkcert \
	&& cd /go/mkcert \
	&& git checkout "tags/${MKCERT_VERSION}" -b "build/${MKCERT_VERSION}" \
	&& go build -ldflags "-X main.Version=${MKCERT_VERSION}" -o /bin/mkcert

WORKDIR /root/.local/share/mkcert
