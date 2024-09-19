FROM node:18 as build-stage

WORKDIR /app

# Copy package.json and package-lock.json (if available)
COPY package*.json ./

RUN npm install -g npm@10.8.3

# Modify package.json to remove the build:icons script
RUN sed -i '/"build:icons"/d' package.json && \
    sed -i 's/"postinstall": "nuxt prepare && npm run build:icons"/"postinstall": "nuxt prepare"/' package.json

# Temporarily disable postinstall script for Docker build
RUN sed -i 's/"postinstall": ".*"/"postinstall": "echo Skipping postinstall for Docker build"/' package.json

# Install dependencies
RUN npm install

# Copy the rest of the application code
COPY . .

# Build the application
RUN npm run build

# Stage 2: Create the production image
FROM node:18 as production-stage

WORKDIR /app

# Copy chỉ các file cần thiết từ giai đoạn build
COPY --from=build-stage /app ./

# Chạy ứng dụng Nuxt.js trong production mode
ENV NODE_ENV=production

# Expose cổng mà Nuxt.js chạy
EXPOSE 3000

# Start server Nuxt.js
# Start the application
CMD ["node", ".output/server/index.mjs"]