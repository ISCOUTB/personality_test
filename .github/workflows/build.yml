name: Build Development Package

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]

jobs:
  build:
    runs-on: ubuntu-latest
    
    steps:
    - name: Checkout code
      uses: actions/checkout@v4
      
    - name: Get version info
      id: get_info
      run: |
        VERSION=$(grep "plugin->release" version.php | sed -n "s/.*'\([^']*\)'.*/\1/p")
        BUILD_NUMBER=$(grep "plugin->version" version.php | sed -n "s/.*= *\([0-9]*\).*/\1/p")
        BRANCH=${GITHUB_REF#refs/heads/}
        COMMIT=${GITHUB_SHA:0:7}
        
        # Sanitize branch name for file naming
        SAFE_BRANCH=$(echo "$BRANCH" | sed 's/[^a-zA-Z0-9._-]/_/g')
        
        echo "version=$VERSION" >> $GITHUB_OUTPUT
        echo "build_number=$BUILD_NUMBER" >> $GITHUB_OUTPUT
        echo "branch=$SAFE_BRANCH" >> $GITHUB_OUTPUT
        echo "commit=$COMMIT" >> $GITHUB_OUTPUT
        
        echo "Building version: $VERSION"
        echo "Build number: $BUILD_NUMBER"
        echo "Branch: $BRANCH (safe: $SAFE_BRANCH)"
        echo "Commit: $COMMIT"
        
    - name: Create development package
      run: |
        # Create package directory
        mkdir -p personality_test_dev_package
        
        # Copy all files except development artifacts
        rsync -av --exclude='.git' --exclude='.github' \
               --exclude='*.md' --exclude='test_*' \
               --exclude='*.log' --exclude='node_modules' \
               ./ personality_test_dev_package/
        
        # Create development ZIP
        cd personality_test_dev_package
        zip -r ../personality_test_dev_${{ steps.get_info.outputs.branch }}_${{ steps.get_info.outputs.commit }}.zip .
        cd ..
        
        echo "Development package created successfully"
        ls -la *.zip
        
    - name: Upload development artifact
      uses: actions/upload-artifact@v4
      with:
        name: personality-test-dev-${{ steps.get_info.outputs.branch }}-${{ steps.get_info.outputs.commit }}
        path: personality_test_dev_${{ steps.get_info.outputs.branch }}_${{ steps.get_info.outputs.commit }}.zip
        retention-days: 30
        
    - name: Validate plugin structure
      run: |
        echo "Validating plugin structure..."
        
        # Check required files
        required_files=(
          "version.php"
          "block_personality_test.php" 
          "lang/en/block_personality_test.php"
          "db/install.xml"
        )
        
        for file in "${required_files[@]}"; do
          if [ -f "personality_test_dev_package/$file" ]; then
            echo "✓ Required file found: $file"
          else
            echo "✗ Missing required file: $file"
            exit 1
          fi
        done
        
        echo "All required files present!"
        
    - name: Run basic syntax check
      run: |
        echo "Running PHP syntax validation..."
        
        # Find and check all PHP files
        find personality_test_dev_package -name "*.php" -exec php -l {} \; | grep -v "No syntax errors detected" || true
        
        echo "Syntax validation completed"
