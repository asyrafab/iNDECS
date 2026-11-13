import { createRequire } from 'node:module';
const runtimeRoot = 'C:/Program Files/WindowsApps/OpenAI.CodexPrimaryRuntime.v26-1104-1290-0_26.1104.1290.0_x64__3k8sg7r9htsxt/dependencies/node/node_modules/';
const require = createRequire(import.meta.url);
const sharp = require(runtimeRoot + 'sharp/dist/index.cjs');
const [input, output, size] = process.argv.slice(2);
await sharp(input, { density: 300 }).resize({ width: Number(size), height: Number(size), fit: 'inside' }).png().toFile(output);
