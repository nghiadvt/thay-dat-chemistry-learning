FROM node:20-alpine

WORKDIR /app

COPY package.json ./
RUN npm install --omit=dev

COPY index.js ./
COPY ws ./ws

ENV WS_PORT=38581
EXPOSE 38581

HEALTHCHECK --interval=10s --timeout=5s --retries=5 \
  CMD wget -qO- http://127.0.0.1:${WS_PORT}/health || exit 1

CMD ["npm", "start"]
