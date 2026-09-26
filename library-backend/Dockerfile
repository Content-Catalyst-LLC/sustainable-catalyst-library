FROM rust:1.90-slim-bookworm AS rust-builder
WORKDIR /native
COPY native-graph-runtime/Cargo.toml native-graph-runtime/Cargo.lock /native/
COPY native-graph-runtime/src /native/src
RUN cargo build --release --locked

FROM python:3.13-slim
ENV PYTHONDONTWRITEBYTECODE=1 PYTHONUNBUFFERED=1 \
    SC_LIBRARY_NATIVE_GRAPH_BIN=/usr/local/bin/sc-library-graph-runtime
WORKDIR /app
RUN useradd --create-home --uid 10001 catalyst
COPY requirements.txt /app/requirements.txt
RUN pip install --no-cache-dir -r /app/requirements.txt
COPY --from=rust-builder /native/target/release/sc-library-graph-runtime /usr/local/bin/sc-library-graph-runtime
COPY app /app/app
USER catalyst
EXPOSE 8080
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8080", "--proxy-headers", "--forwarded-allow-ips", "*"]
