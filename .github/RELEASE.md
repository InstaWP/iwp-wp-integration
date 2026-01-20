# Release Process

## Automated Release via GitHub Actions

This plugin uses GitHub Actions to automatically build and release new versions.

## How to Create a Release

### 1. Update Version Number

Update the version in the following files:

**iwp-wp-integration.php** (line 12):
```php
* Version:          0.0.3
```

**iwp-wp-integration.php** (line 51):
```php
define('IWP_VERSION', '0.0.3');
```

### 2. Update Changelog

Add release notes to `CHANGELOG.md` and `README.md`:

**CHANGELOG.md:**
```markdown
## [0.0.3] - 2025-01-20

### Added
- Feature descriptions...

### Fixed
- Bug fix descriptions...
```

**README.md:**
```markdown
### Version 0.0.3
- Brief feature list...
```

### 3. Commit Changes

```bash
git add -A
git commit -m "Bump version to 0.0.3"
git push origin your-branch
```

### 4. Create and Push Tag

```bash
git tag v0.0.3
git push origin v0.0.3
```

### 5. Automatic Build

The GitHub Action will automatically:
1. ✅ Verify version matches tag
2. ✅ Create clean plugin directory
3. ✅ Exclude development files
4. ✅ Generate plugin zip file
5. ✅ Create GitHub release
6. ✅ Upload zip as release asset
7. ✅ Extract changelog from CHANGELOG.md

### 6. Download Release

The plugin zip will be available at:
```
https://github.com/InstaWP/iwp-wp-integration/releases/download/v0.0.3/iwp-wp-integration-0.0.3.zip
```

## Excluded Files

The following files/directories are excluded from releases:

### Development Files
- `.git/` - Git repository data
- `.github/` - GitHub workflows and configs
- `.gitignore` - Git ignore file
- `.gitattributes` - Git attributes

### Documentation (Excluded)
- `CLAUDE.md` - Internal development documentation
- `TESTING-PLAN.md` - Testing documentation
- `MIGRATION-GUIDE.md` - Migration documentation
- `DEBUG-SCRIPTS.md` - Debug documentation

### Documentation (Included)
- `README.md` - ✅ User-facing documentation (INCLUDED in release)
- `CHANGELOG.md` - ✅ Version history (INCLUDED in release)

### Debug/Test Files
- `debug-*.php` - Debug scripts
- `test.php` - Test files
- `admin-migrate.php` - Admin migration tool
- `migrate-db.php` - Database migration script
- `phpunit.xml` - PHPUnit configuration
- `tests/` - Test directory

### Dependencies
- `node_modules/` - Node dependencies
- `vendor/` - Composer dependencies
- `composer.json` - Composer config
- `composer.lock` - Composer lock
- `package.json` - NPM config
- `package-lock.json` - NPM lock

### System Files
- `.DS_Store` - macOS system files
- `*.log` - Log files

## Versioning

This project follows [Semantic Versioning](https://semver.org/):

- **MAJOR** version (x.0.0): Incompatible API changes
- **MINOR** version (0.x.0): New functionality (backward compatible)
- **PATCH** version (0.0.x): Bug fixes (backward compatible)

## Release Checklist

- [ ] Update version in `iwp-wp-integration.php` (header and constant)
- [ ] Update `CHANGELOG.md` with release notes
- [ ] Update `README.md` changelog section
- [ ] Test plugin thoroughly
- [ ] Commit all changes
- [ ] Create and push version tag
- [ ] Verify GitHub Action completed successfully
- [ ] Download and test release zip
- [ ] Update WordPress.org (if applicable)

## Manual Release (Fallback)

If the automated process fails, you can create a release manually:

```bash
# Create clean directory
mkdir -p build/iwp-wp-integration

# Copy files (excluding development files)
rsync -av . build/iwp-wp-integration/ \
  --exclude='.git' \
  --exclude='.github' \
  --exclude='node_modules' \
  --exclude='*.log' \
  --exclude='debug-*.php' \
  --exclude='test.php'

# Create zip
cd build
zip -r iwp-wp-integration-0.0.3.zip iwp-wp-integration/

# Upload to GitHub Releases manually
```

## Troubleshooting

### Version Mismatch Error

If the workflow fails with "Plugin version does not match tag version":
1. Check the version in `iwp-wp-integration.php` header
2. Ensure it matches the git tag (without 'v' prefix)
3. Commit the correction and create a new tag

### Failed to Create Release

If release creation fails:
1. Check GitHub Actions logs for errors
2. Verify you have push permissions to the repository
3. Ensure GITHUB_TOKEN has release permissions

### Missing Files in Zip

If files are missing from the release:
1. Check the `rsync` exclude list in the workflow
2. Ensure files are committed to git
3. Verify `.gitignore` isn't excluding needed files

## Support

For issues with the release process:
- Check GitHub Actions logs: Repository → Actions tab
- Review workflow file: `.github/workflows/release.yml`
- Contact repository administrators
