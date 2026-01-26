#!/usr/bin/env python3
"""Verify vendor directory has all required dependencies."""

import sys
from pathlib import Path

# Try to use rich if available, fall back to basic output
try:
    from rich.console import Console
    from rich.table import Table
    console = Console()
    USE_RICH = True
except ImportError:
    USE_RICH = False
    
    class Console:
        def print(self, *args, **kwargs):
            # Strip rich formatting
            text = str(args[0]) if args else ""
            text = text.replace("[bold blue]", "").replace("[/bold blue]", "")
            text = text.replace("[bold green]", "").replace("[/bold green]", "")
            text = text.replace("[bold red]", "").replace("[/bold red]", "")
            text = text.replace("[green]", "").replace("[/green]", "")
            text = text.replace("[red]", "").replace("[/red]", "")
            text = text.replace("[yellow]", "").replace("[/yellow]", "")
            text = text.replace("[cyan]", "").replace("[/cyan]", "")
            text = text.replace("[white]", "").replace("[/white]", "")
            print(text)
    
    console = Console()


def check_vendor_integrity():
    """Check if vendor directory has all required packages."""
    backend_dir = Path(__file__).parent.parent / "backend"
    vendor_dir = backend_dir / "vendor"
    
    console.print("[bold blue]Vendor Directory Integrity Check[/bold blue]\n")
    
    if not vendor_dir.exists():
        console.print("[red]✗ vendor/ directory not found![/red]")
        console.print("[yellow]Run: cd backend && composer install[/yellow]")
        return False
    
    # Required packages from composer.json
    required_packages = {
        "slim/slim": "Slim Framework",
        "slim/psr7": "PSR-7 implementation",
        "google/apiclient": "Google API Client",
        "firebase/php-jwt": "JWT library",
        "symfony/yaml": "YAML parser",
        "vlucas/phpdotenv": "Environment loader"
    }
    
    # Create results table
    if USE_RICH:
        table = Table(show_header=True, header_style="bold blue")
        table.add_column("Package", style="cyan")
        table.add_column("Description", style="white")
        table.add_column("Status", style="green")
    
    all_found = True
    
    for package, description in required_packages.items():
        package_path = vendor_dir / package.replace("/", "/")
        
        if package_path.exists():
            status = "[green]✓ Found[/green]"
            status_plain = "✓ Found"
        else:
            status = "[red]✗ Missing[/red]"
            status_plain = "✗ Missing"
            all_found = False
        
        if USE_RICH:
            table.add_row(package, description, status)
        else:
            console.print(f"{package:30} {description:30} {status_plain}")
    
    if USE_RICH:
        console.print(table)
    console.print()
    
    # Check autoload.php
    autoload = vendor_dir / "autoload.php"
    if autoload.exists():
        console.print("[green]✓[/green] vendor/autoload.php exists")
    else:
        console.print("[red]✗[/red] vendor/autoload.php missing")
        all_found = False
    
    # Check composer directory
    composer_dir = vendor_dir / "composer"
    if composer_dir.exists():
        console.print("[green]✓[/green] vendor/composer/ exists")
    else:
        console.print("[red]✗[/red] vendor/composer/ missing")
        all_found = False
    
    console.print()
    
    if all_found:
        console.print("[bold green]✓ All dependencies are present![/bold green]")
        console.print("[green]Ready for deployment.[/green]")
        return True
    else:
        console.print("[bold red]✗ Some dependencies are missing![/bold red]")
        console.print("[yellow]Run: cd backend && composer install[/yellow]")
        return False


def get_vendor_stats():
    """Get statistics about vendor directory."""
    backend_dir = Path(__file__).parent.parent / "backend"
    vendor_dir = backend_dir / "vendor"
    
    if not vendor_dir.exists():
        return
    
    console.print("\n[bold blue]Vendor Directory Statistics[/bold blue]\n")
    
    # Count packages
    vendor_packages = [d for d in vendor_dir.iterdir() 
                      if d.is_dir() and not d.name.startswith('.') and d.name != 'composer']
    
    total_packages = 0
    for vendor_namespace in vendor_packages:
        packages = [d for d in vendor_namespace.iterdir() if d.is_dir()]
        total_packages += len(packages)
    
    console.print(f"[cyan]Total packages:[/cyan] {total_packages}")
    console.print(f"[cyan]Vendor namespaces:[/cyan] {len(vendor_packages)}")
    
    # Calculate size
    total_size = 0
    for file_path in vendor_dir.rglob('*'):
        if file_path.is_file():
            total_size += file_path.stat().st_size
    
    size_mb = total_size / (1024 * 1024)
    console.print(f"[cyan]Total size:[/cyan] {size_mb:.2f} MB")
    
    # Check if dev dependencies are included
    phpunit_path = vendor_dir / "phpunit"
    if phpunit_path.exists():
        console.print("[yellow]⚠ Warning: Dev dependencies detected (PHPUnit)[/yellow]")
        console.print("[yellow]For production, run: composer install --no-dev[/yellow]")
    else:
        console.print("[green]✓ Production-ready (no dev dependencies)[/green]")


def main():
    """Main verification function."""
    success = check_vendor_integrity()
    get_vendor_stats()
    
    console.print()
    
    if success:
        sys.exit(0)
    else:
        sys.exit(1)


if __name__ == "__main__":
    main()
