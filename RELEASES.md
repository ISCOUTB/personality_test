# Personality Test Block - Automated Releases

This repository includes automated GitHub Actions workflows for building and releasing the Personality Test block.

## 🚀 Release System

### Automatic Releases
- **Triggers**: When you push to the `main` branch or manually trigger the workflow
- **Version Detection**: Automatically reads version from `version.php`
- **Tag Creation**: Creates GitHub tags like `v1.0.0` based on the release version
- **ZIP Package**: Generates a ready-to-install ZIP file
- **Release Notes**: Auto-generates detailed release information

### Development Builds
- **Triggers**: Pushes to `main` or `develop` branches
- **Artifacts**: Creates development packages with commit info
- **Retention**: Keeps builds for 30 days
- **Build Info**: Includes detailed build information file

## 📦 Creating a Release

### Method 1: Version Bump (Recommended)
1. Update the version in `version.php`:
   ```php
   $plugin->version   = 2025090800;  // Update this
   $plugin->release   = '1.1.0';     // And this
   ```
2. Commit and push to `main`
3. GitHub Actions will automatically:
   - Create a new tag (e.g., `v1.1.0`)
   - Build the ZIP package
   - Create a GitHub release
   - Upload the package as a release asset

### Method 2: Manual Trigger
1. Go to the "Actions" tab in GitHub
2. Select "Create Release Package"
3. Click "Run workflow"
4. Choose the branch and click "Run workflow"

## 📋 Version Format

The system expects this format in `version.php`:
```php
$plugin->version   = 2025090800;  // YYYYMMDDXX format
$plugin->release   = '1.1.0';     // Semantic versioning
```

## 🎯 What's Included in Releases

Each release package includes:
- Complete Personality Test block
- MBTI assessment with 72 questions
- CSV and PDF export functionality
- Teacher dashboard with analytics
- Interactive charts and visualizations
- Multi-language support (English/Spanish)
- Database installation scripts
- All necessary assets and dependencies

## 📁 Package Structure

```
block_personality_test_v1.0.0.zip
└── block_personality_test/
    ├── block_personality_test.php
    ├── version.php
    ├── download_csv.php
    ├── download_pdf.php
    ├── lang/
    ├── db/
    ├── amd/
    ├── pix/
    └── ...
```

## 🔧 Workflow Files

- `.github/workflows/release.yml` - Handles official releases
- `.github/workflows/build.yml` - Handles development builds

## 📝 Release Notes

GitHub releases include:
- Installation instructions
- Version details
- Feature list
- Changelog information
- Download links

## 🚦 Status

You can monitor the build status in the "Actions" tab of the GitHub repository.

---

For questions about the release system, check the GitHub Actions logs or contact the development team.
