import esbuild from 'esbuild'

const isDev = process.argv.includes('--dev')

async function compile(options) {
    const context = await esbuild.context(options)

    if (isDev) {
        await context.watch()
    } else {
        await context.rebuild()
        await context.dispose()
    }
}

const defaultOptions = {
    define: {
        'process.env.NODE_ENV': isDev ? `'development'` : `'production'`,
    },
    bundle: true,
    mainFields: ['module', 'main'],
    platform: 'neutral',
    sourcemap: isDev ? 'inline' : false,
    sourcesContent: isDev,
    treeShaking: true,
    target: ['es2020'],
    minify: !isDev,
}

// The Alpine component, loaded on demand through Filament's x-load.
compile({
    ...defaultOptions,
    entryPoints: ['./resources/js/splitbutton.js'],
    outfile: './resources/dist/components/splitbutton.js',
}).then(() => console.log('Built resources/dist/components/splitbutton.js'))

compile({
    ...defaultOptions,
    entryPoints: ['./resources/css/splitbutton.css'],
    outfile: './resources/dist/splitbutton.css',
}).then(() => console.log('Built resources/dist/splitbutton.css'))
