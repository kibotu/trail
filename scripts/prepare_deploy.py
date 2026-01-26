#!/usr/bin/env -S uv run python
"""Prepare backend for production deployment."""

import subprocess
import sys
from pathlib import Path
from rich.console import Console

console = Console()


def run_composer_install():
    """Run composer install with production flags."""
    backend_dir = Path(__file__).parent.parent / "backend"
    
    if not backend_dir.exists():
        console.print(f"[red]Error: Backend directory not found at {backend_dir}[/red]")
        sys.exit(1)
    
    console.print("[bold blue]Installing Composer dependencies for production...[/bold blue]\n")
    
    try:
        result = subprocess.run(
            ["composer", "install", "--no-dev", "--optimize-autoloader", "--no-interaction"],
            cwd=backend_dir,
            check=True,
            capture_output=True,
            text=True
        )
        
        console.print(result.stdout)
        console.print("[green]✓ Composer dependencies installed successfully[/green]\n")
        
        # Verify vendor directory
        vendor_dir = backend_dir / "vendor"
        if vendor_dir.exists():
            autoload = vendor_dir / "autoload.php"
            if autoload.exists():
                console.print(f"[green]✓ vendor/autoload.php found[/green]")
                
                # Count vendor packages
                vendor_subdirs = [d for d in vendor_dir.iterdir() if d.is_dir() and not d.name.startswith('.')]
                console.print(f"[green]✓ {len(vendor_subdirs)} vendor packages installed[/green]\n")
            else:
                console.print("[red]✗ vendor/autoload.php not found![/red]")
                sys.exit(1)
        else:
            console.print("[red]✗ vendor/ directory not created![/red]")
            sys.exit(1)
            
    except subprocess.CalledProcessError as e:
        console.print(f"[red]Error running composer install:[/red]")
        console.print(e.stderr)
        sys.exit(1)
    except FileNotFoundError:
        console.print("[red]Error: Composer not found![/red]")
        console.print("[yellow]Install Composer: https://getcomposer.org/download/[/yellow]")
        console.print("[yellow]Or use Docker: docker compose exec backend composer install --no-dev --optimize-autoloader[/yellow]")
        sys.exit(1)


def verify_production_ready():
    """Verify backend is ready for production deployment."""
    backend_dir = Path(__file__).parent.parent / "backend"
    
    console.print("[bold blue]Verifying production readiness...[/bold blue]\n")
    
    checks = []
    
    # Check 1: vendor directory exists
    vendor_dir = backend_dir / "vendor"
    if vendor_dir.exists():
        checks.append(("vendor/ directory", True))
    else:
        checks.append(("vendor/ directory", False))
    
    # Check 2: composer.lock exists
    composer_lock = backend_dir / "composer.lock"
    if composer_lock.exists():
        checks.append(("composer.lock", True))
    else:
        checks.append(("composer.lock", False))
    
    # Check 3: autoload.php exists
    autoload = vendor_dir / "autoload.php" if vendor_dir.exists() else None
    if autoload and autoload.exists():
        checks.append(("vendor/autoload.php", True))
    else:
        checks.append(("vendor/autoload.php", False))
    
    # Check 4: Required packages
    required_packages = [
        "slim/slim",
        "google/apiclient",
        "firebase/php-jwt",
        "vlucas/phpdotenv"
    ]
    
    for package in required_packages:
        package_dir = vendor_dir / package.replace("/", "/") if vendor_dir.exists() else None
        package_exists = package_dir and package_dir.exists()
        checks.append((f"Package: {package}", package_exists))
    
    # Print results
    all_passed = True
    for check_name, passed in checks:
        if passed:
            console.print(f"[green]✓[/green] {check_name}")
        else:
            console.print(f"[red]✗[/red] {check_name}")
            all_passed = False
    
    console.print()
    
    if all_passed:
        console.print("[bold green]✓ Backend is ready for production deployment![/bold green]\n")
        console.print("[yellow]Next step:[/yellow] cd scripts && uv run python full_deploy.py")
    else:
        console.print("[bold red]✗ Backend is NOT ready for deployment[/bold red]\n")
        console.print("[yellow]Run: composer install --no-dev --optimize-autoloader[/yellow]")
        sys.exit(1)


def check_php_version():
    """Check PHP version matches production (8.4)."""
    console.print("[bold blue]Checking PHP version...[/bold blue]\n")
    
    try:
        result = subprocess.run(
            ["php", "-v"],
            capture_output=True,
            text=True,
            check=True
        )
        
        if "PHP 8.4" in result.stdout:
            console.print("[green]✓ PHP 8.4 detected[/green]\n")
            return True
        else:
            console.print("[yellow]⚠ Warning: PHP version may not match production (8.4)[/yellow]")
            console.print(f"[yellow]Detected: {result.stdout.split()[1]}[/yellow]\n")
            return True  # Don't fail, just warn
    except Exception as e:
        console.print(f"[yellow]⚠ Could not check PHP version: {e}[/yellow]\n")
        return True  # Don't fail


def main():
    """Prepare backend for deployment."""
    console.print("[bold blue]Trail Production Deployment Preparation[/bold blue]\n")
    
    # Step 0: Check PHP version
    check_php_version()
    
    # Step 1: Install dependencies
    run_composer_install()
    
    # Step 2: Verify everything is ready
    verify_production_ready()


if __name__ == "__main__":
    main()
