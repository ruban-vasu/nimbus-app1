# --- Build stage ---
FROM node:20-alpine AS build

WORKDIR /app

# Install dependencies first (layer cache)
COPY frontend/package*.json ./
RUN npm ci

# Copy source and build
COPY frontend/ .
RUN npm run build

# --- Production stage: serve with Nginx ---
FROM nginx:stable-alpine AS production

COPY --from=build /app/dist /usr/share/nginx/html

# Default Nginx config — proxies /api to the Laravel service
COPY docker/frontend/nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
