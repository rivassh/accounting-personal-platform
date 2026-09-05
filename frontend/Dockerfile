FROM node:20-alpine

WORKDIR /app

COPY package.json pnpm-lock.yaml* ./

RUN corepack enable && corepack prepare pnpm@latest --activate

RUN pnpm install --frozen-lockfile || pnpm install

COPY . .

EXPOSE 3000

ENV NODE_ENV=development

CMD ["pnpm", "dev", "--host", "0.0.0.0"]
