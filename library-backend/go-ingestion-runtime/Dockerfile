FROM golang:1.23-bookworm AS builder
WORKDIR /src
COPY go.mod ./
COPY main.go main_test.go ./
RUN go test ./... && CGO_ENABLED=0 go build -trimpath -ldflags='-s -w' -o /out/sc-library-ingestion-runtime . \
    && mkdir -p /out/data && touch /out/data/.keep

FROM gcr.io/distroless/static-debian12:nonroot
COPY --from=builder /out/sc-library-ingestion-runtime /sc-library-ingestion-runtime
COPY --chown=65532:65532 --from=builder /out/data /data
EXPOSE 8090
ENTRYPOINT ["/sc-library-ingestion-runtime"]
