# Passkey browser client (vendored)

The browser half of Fortify's passkeys (roadmap TODO 69). This project has no
JavaScript build (roadmap TODO 52 removed it), so the official client is shipped
as plain ES modules and loaded through an import map
(`resources/views/partials/passkeys-client.blade.php`).

The files are **byte-identical to the npm packages** - copied out of the
registry tarballs, nothing edited. The import map is what resolves the one bare
import (`@simplewebauthn/browser`) in `laravel-passkeys/passkeys-*.js`.

| Directory | Package | Tarball integrity (npm registry) | Licence |
|---|---|---|---|
| `laravel-passkeys/` | `@laravel/passkeys` 0.4.0, `dist/index.js` and its chunk | `sha512-59UHJHZPrIT8f5hFQJUR1EXhazWZTg3DcJefxSfA3aRj6xu40I/lYDveUY4oL2/h/crq9jjptSKykZDDold7aA==` | MIT (declared in its `package.json`; the tarball ships no licence file) |
| `simplewebauthn-browser/` | `@simplewebauthn/browser` 13.3.0, `esm/` | `sha512-BE/UWv6FOToAdVk0EokzkqQQDOWtNydYlY6+OrmiZ5SCNmb41VehttboTetUM3T/fr6EAFYVXjz4My2wg230rQ==` | MIT (`LICENSE.md`) |

Each directory keeps the package's own `package.json` as the record of what it is.

## Updating

1. Download the new tarballs from `https://registry.npmjs.org/<package>/-/<file>.tgz`
   into an empty directory and check them against `dist.integrity` from
   `https://registry.npmjs.org/<package>/<version>`.
2. Replace the files here with the same subset (`dist/index.js` plus the chunk it
   imports; the whole `esm/` tree). The chunk's hashed file name changes between
   releases - `index.js` names it.
3. Update this table and the import map, and run `composer test`:
   `PasskeyClientAssetsTest` checks that every relative import resolves to a file
   here and that the bare import is mapped.
