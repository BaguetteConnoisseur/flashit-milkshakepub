FROM node:18-alpine
WORKDIR /app
RUN npm install ws cookie node-fetch
COPY server.js .
EXPOSE 8081
EXPOSE 8082
CMD ["node", "server.js"]