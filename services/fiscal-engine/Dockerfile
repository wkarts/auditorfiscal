FROM python:3.13-slim
ENV PYTHONDONTWRITEBYTECODE=1 PYTHONUNBUFFERED=1 PIP_NO_CACHE_DIR=1
RUN apt-get update && apt-get install -y --no-install-recommends libxml2 libxslt1.1 fontconfig && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY . .
RUN pip install .
RUN useradd -r -u 10001 auditor && chown -R auditor:auditor /app
USER auditor
EXPOSE 8000
CMD ["uvicorn","app.main:app","--host","0.0.0.0","--port","8000","--workers","2","--proxy-headers"]
