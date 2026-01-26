#!/usr/bin/env python3
"""Verify deployment readiness - checks Docker, vendor, and configuration."""

import sys
import subprocess
from pathlib import Path

def print_header(text):
    """Print a formatted header."""
    print(f"\n{'='*60}")
    print(f"  {text}")
    print(f"{'='*60}\n")

def print_status(check, passed, message=""):
    """Print a check status."""
    status = "✅" if passed else "❌"
    print(f"{status} {check}")
    if message:
        print(f"   {message}")
    return passed

def check_docker_running():
    """Check if Docker is running."""
    try:
        result = subprocess.run(
            ["docker", "compose", "ps"],
            capture_output=True,
            text=True,
            timeout=5
        )
        return result.returncode == 0
    except Exception:
        return False

def check_vendor_exists():
    """Check if vendor directory exists."""
    backend_dir = Path(__file__).parent.parent / "backend"
    vendor_dir = backend_dir / "vendor"
    return vendor_dir.exists() and (vendor_dir / "autoload.php").exists()

def check_composer_lock():
    """Check if composer.lock exists."""
    backend_dir = Path(__file__).parent.parent / "backend"
    return (backend_dir / "composer.lock").exists()

def check_env_file():
    """Check if .env file exists."""
    root_dir = Path(__file__).parent.parent
    return (root_dir / ".env").exists()

def check_config_file():
    """Check if config.yml exists."""
    root_dir = Path(__file__).parent.parent
    return (root_dir / "config.yml").exists()

def check_gitignore():
    """Check if vendor is in .gitignore."""
    root_dir = Path(__file__).parent.parent
    gitignore = root_dir / ".gitignore"
    if gitignore.exists():
        content = gitignore.read_text()
        return "backend/vendor/" in content
    return False

def check_php_extensions():
    """Check if required PHP extensions are available in Docker."""
    required = ["curl", "mysqli", "pdo_mysql", "mbstring", "json", "gd", "zip", "bcmath", "intl", "opcache"]
    
    try:
        result = subprocess.run(
            ["docker", "compose", "exec", "-T", "backend", "php", "-m"],
            capture_output=True,
            text=True,
            timeout=10
        )
        
        if result.returncode != 0:
            return False, []
        
        loaded_extensions = [line.strip().lower() for line in result.stdout.split('\n')]
        missing = [ext for ext in required if ext.lower() not in loaded_extensions]
        
        return len(missing) == 0, missing
    except Exception:
        return False, []

def main():
    """Run all deployment readiness checks."""
    print_header("Deployment Readiness Check")
    
    all_passed = True
    
    # Check 1: Docker
    print("🐳 Docker Environment")
    print("-" * 60)
    docker_ok = check_docker_running()
    all_passed &= print_status(
        "Docker is running",
        docker_ok,
        "Run: docker compose up -d" if not docker_ok else ""
    )
    
    # Check 2: Dependencies
    print("\n📦 Dependencies")
    print("-" * 60)
    vendor_ok = check_vendor_exists()
    all_passed &= print_status(
        "vendor/ directory exists",
        vendor_ok,
        "Run: cd backend && composer install" if not vendor_ok else ""
    )
    
    composer_lock_ok = check_composer_lock()
    all_passed &= print_status(
        "composer.lock exists",
        composer_lock_ok,
        "Run: cd backend && composer install" if not composer_lock_ok else ""
    )
    
    # Check 3: Configuration
    print("\n⚙️  Configuration")
    print("-" * 60)
    env_ok = check_env_file()
    all_passed &= print_status(
        ".env file exists",
        env_ok,
        "Copy: cp .env.example .env" if not env_ok else ""
    )
    
    config_ok = check_config_file()
    all_passed &= print_status(
        "config.yml exists",
        config_ok,
        "Copy: cp config.yml.example config.yml" if not config_ok else ""
    )
    
    # Check 4: Git Configuration
    print("\n🔧 Git Configuration")
    print("-" * 60)
    gitignore_ok = check_gitignore()
    all_passed &= print_status(
        "vendor/ in .gitignore",
        gitignore_ok,
        "Add 'backend/vendor/' to .gitignore" if not gitignore_ok else ""
    )
    
    # Check 5: PHP Extensions (if Docker is running)
    if docker_ok:
        print("\n🔌 PHP Extensions")
        print("-" * 60)
        ext_ok, missing = check_php_extensions()
        all_passed &= print_status(
            "Required PHP extensions",
            ext_ok,
            f"Missing: {', '.join(missing)}" if not ext_ok else "All required extensions loaded"
        )
    
    # Summary
    print_header("Summary")
    
    if all_passed:
        print("✅ All checks passed! Ready for deployment.")
        print("\nNext steps:")
        print("  1. Test locally: ./run.sh")
        print("  2. Deploy: cd scripts && uv run python full_deploy.py")
        return 0
    else:
        print("❌ Some checks failed. Fix the issues above before deploying.")
        return 1

if __name__ == "__main__":
    sys.exit(main())
