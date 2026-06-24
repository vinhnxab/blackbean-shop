/**
 * Build Black Bean Shop plugin assets.
 *
 * Usage (from plugin folder):
 *   npm run build
 *
 * Or from site root:
 *   node wp-content/plugins/blackbean-shop/scripts/build.mjs
 */
import { spawnSync } from 'child_process';
import path from 'path';
import { fileURLToPath } from 'url';

const root = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..' );

function run( label, cmd, args ) {
	console.log( `\n==> ${ label }` );
	const result = spawnSync( cmd, args, {
		cwd: root,
		stdio: 'inherit',
		shell: process.platform === 'win32',
	} );
	if ( result.status !== 0 ) {
		process.exit( result.status ?? 1 );
	}
}

run(
	'blackbean-shop — shop-admin.css',
	'npx',
	[
		'tailwindcss',
		'-c',
		'./tailwind.config.js',
		'-i',
		'./src/shop-admin.css',
		'-o',
		'./assets/css/shop-admin.css',
		'--minify',
	]
);

console.log( '\nblackbean-shop build finished.' );
