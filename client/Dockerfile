FROM node:18

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

EXPOSE 3000

CMD ["npm", "start"]