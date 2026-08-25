if (process.platform === 'win32') {
    process.env.NAPI_RS_FORCE_WASI ??= 'true';
}

const { build } = await import('vite');

await build();
