import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { compile } from '@tailwindcss/node';
import fs from 'node:fs';
import path from 'node:path';

const contentRoots = [
    path.resolve('resources/views'),
    path.resolve('resources/js'),
    path.resolve('vendor/laravel/framework/src/Illuminate/Pagination/resources/views'),
];

const cssEntries = [
    path.resolve('resources/css/app.css'),
    path.resolve('resources/css/filament/admin/theme.css'),
];

function contentFiles(directory) {
    if (! fs.existsSync(directory)) {
        return [];
    }

    return fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        const file = path.join(directory, entry.name);

        return entry.isDirectory() ? contentFiles(file) : [file];
    });
}

function tisiloTailwind() {
    return {
        name: 'tisilo-tailwind',
        enforce: 'pre',
        async transform(source, id) {
            if (! cssEntries.includes(path.normalize(id))) {
                return null;
            }

            const files = contentRoots.flatMap(contentFiles);
            const candidates = new Set();

            for (const file of files) {
                this.addWatchFile(file);

                const content = fs.readFileSync(file, 'utf8');
                for (const candidate of content.match(/[A-Za-z0-9_!@#%&?+~=<>.:/\\[\](),-]+/g) ?? []) {
                    candidates.add(candidate);
                }
            }

            const compiler = await compile(source, {
                base: path.dirname(id),
                from: id,
                onDependency: (dependency) => this.addWatchFile(dependency),
            });

            return {
                code: compiler.build([...candidates]),
                map: null,
            };
        },
    };
}

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/filament/admin/theme.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
        tisiloTailwind(),
    ],
});
