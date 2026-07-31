import { readdir, readFile } from 'node:fs/promises';
import { extname, join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import { parse } from '@vue/compiler-sfc';

const root = fileURLToPath(new URL('../src', import.meta.url));
const failures = [];

async function walk(directory) {
  const entries = await readdir(directory, { withFileTypes: true });

  for (const entry of entries) {
    const path = join(directory, entry.name);

    if (entry.isDirectory()) {
      await walk(path);
      continue;
    }

    if (extname(entry.name) !== '.vue') {
      continue;
    }

    const source = await readFile(path, 'utf8');
    const result = parse(source, { filename: path });

    for (const error of result.errors) {
      failures.push({
        file: relative(root, path),
        message: typeof error === 'string' ? error : error.message,
      });
    }
  }
}

await walk(root);

if (failures.length > 0) {
  console.error('Foram encontrados componentes Vue inválidos:');
  for (const failure of failures) {
    console.error(`- ${failure.file}: ${failure.message}`);
  }
  process.exit(1);
}

console.log('Todos os componentes Vue SFC são estruturalmente válidos.');
