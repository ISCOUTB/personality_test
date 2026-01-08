name: Automated Release

on:
  push:
    branches: [ main ]
    paths:
      - 'version.php'
  workflow_dispatch:
    inputs:
      force_release:
        description: 'Force release even if version exists'
        required: false
        type: boolean
        default: false

jobs:
  check-version:
    runs-on: ubuntu-latest
    outputs:
      should-release: ${{ steps.version-check.outputs.should-release }}
      version: ${{ steps.version-check.outputs.version }}
      release-name: ${{ steps.version-check.outputs.release-name }}
    steps:
      - name: Checkout code
        uses: actions/checkout@v4
        with:
          fetch-depth: 0

      - name: Check if version changed
        id: version-check
        run: |
          # Extraer versión actual del archivo version.php
          CURRENT_VERSION=$(grep -oP "(?<=\\\$plugin->release = ')[^']*" version.php)
          echo "Current version: $CURRENT_VERSION"
          
          # Verificar si ya existe un tag para esta versión (a menos que sea forzado)
          if [ "${{ github.event.inputs.force_release }}" = "true" ]; then
            echo "Force release enabled, proceeding..."
            echo "should-release=true" >> $GITHUB_OUTPUT
          elif git tag --list | grep -q "^v$CURRENT_VERSION$"; then
            echo "Tag v$CURRENT_VERSION already exists, skipping release"
            echo "should-release=false" >> $GITHUB_OUTPUT
          else
            echo "New version detected: $CURRENT_VERSION"
            echo "should-release=true" >> $GITHUB_OUTPUT
          fi
          
          echo "version=$CURRENT_VERSION" >> $GITHUB_OUTPUT
          echo "release-name=Personality Test Block v$CURRENT_VERSION" >> $GITHUB_OUTPUT

  create-release:
    needs: check-version
    if: needs.check-version.outputs.should-release == 'true'
    runs-on: ubuntu-latest
    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@44454db4f0199b8b9685a5d763dc37cbf79108e1
        with:
          php-version: '8.1'

      - name: Validate PHP syntax
        run: |
          echo "Validating PHP syntax..."
          find . -name "*.php" -exec php -l {} \; || exit 1

      - name: Create release archive
        run: |
          # Crear directorio temporal para el release
          mkdir -p release-temp/personality_test
          
          # Copiar archivos del plugin (excluyendo .git y archivos de desarrollo)
          rsync -av \
            --exclude='.git*' \
            --exclude='release-temp' \
            --exclude='*.log' \
            --exclude='node_modules' \
            --exclude='.DS_Store' \
            ./ release-temp/personality_test/
          
          # Crear archivo ZIP
          cd release-temp
          zip -r ../personality_test-v${{ needs.check-version.outputs.version }}.zip personality_test/
          cd ..
          
          # Verificar que el archivo se creó
          ls -la personality_test-v${{ needs.check-version.outputs.version }}.zip
          
          # Limpiar directorio temporal
          rm -rf release-temp

      - name: Extract changelog for version
        id: changelog
        run: |
          # Extraer changelog para la versión actual
          if [ -f "CHANGELOG.md" ]; then
            # Buscar la sección de la versión actual
            awk '/## \['"${{ needs.check-version.outputs.version }}"'\]/{flag=1; next} /## \[/{flag=0} flag' CHANGELOG.md > current_changelog.md
            
            if [ -s current_changelog.md ]; then
              echo "Found changelog entry for version ${{ needs.check-version.outputs.version }}"
              echo "Changelog content:"
              cat current_changelog.md
            else
              echo "No specific changelog entry found, using default message"
              echo "### Cambios en esta versión" > current_changelog.md
              echo "- Consultar CHANGELOG.md para detalles completos" >> current_changelog.md
              echo "- Version: ${{ needs.check-version.outputs.version }}" >> current_changelog.md
            fi
          else
            echo "### Cambios en esta versión" > current_changelog.md
            echo "- Nueva versión del bloque Personality Test" >> current_changelog.md
            echo "- Version: ${{ needs.check-version.outputs.version }}" >> current_changelog.md
          fi

      - name: Create Git tag
        run: |
          git config --local user.email "action@github.com"
          git config --local user.name "GitHub Action"
          git tag -a "v${{ needs.check-version.outputs.version }}" -m "Release v${{ needs.check-version.outputs.version }}"
          git push origin "v${{ needs.check-version.outputs.version }}"

      - name: Create GitHub Release
        id: create_release
        uses: actions/create-release@v1
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
        with:
          tag_name: v${{ needs.check-version.outputs.version }}
          release_name: ${{ needs.check-version.outputs.release-name }}
          body_path: current_changelog.md
          draft: false
          prerelease: false

      - name: Upload Release Asset
        uses: actions/upload-release-asset@v1
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
        with:
          upload_url: ${{ steps.create_release.outputs.upload_url }}
          asset_path: ./personality_test-v${{ needs.check-version.outputs.version }}.zip
          asset_name: personality_test-v${{ needs.check-version.outputs.version }}.zip
          asset_content_type: application/zip

      - name: Success notification
        run: |
          echo "✅ Release v${{ needs.check-version.outputs.version }} created successfully!"
          echo "📦 Asset: personality_test-v${{ needs.check-version.outputs.version }}.zip"
          echo "🔗 Release URL: https://github.com/${{ github.repository }}/releases/tag/v${{ needs.check-version.outputs.version }}"
