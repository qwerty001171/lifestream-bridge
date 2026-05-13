FROM node:20-alpine
WORKDIR /app
COPY billing-mock/server.js .
COPY billing-mock/request.json .
EXPOSE 8081
CMD ["node", "server.js"]
