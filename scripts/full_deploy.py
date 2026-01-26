#!/usr/bin/env -S uv run python
"""Full deployment script for Trail service."""

import sys
import subprocess
from pathlib import Path
from rich.console import Console

console = Console()


def run_script(script_name, description):
    """Run a deployment script."""
    console.print(f"\n[bold blue]{description}[/bold blue]")
    console.print("=" * 60)
    
    script_path = Path(__file__).parent / f"{script_name}.py"
    
    try:
        result = subprocess.run(
            ["uv", "run", "python", str(script_path)],
            check=True,
            capture_output=False
        )
        console.print(f"[green]✓[/green] {description} completed successfully\n")
        return True
    except subprocess.CalledProcessError as e:
        console.print(f"[red]✗[/red] {description} failed\n")
        return False


def verify_backend_health(config):
    """Verify backend is responding."""
    import requests
    
    try:
        base_url = config['app']['base_url']
        response = requests.get(f"{base_url}/health", timeout=10)
        
        if response.status_code == 200:
            console.print(f"[green]✓[/green] Backend health check passed")
            return True
        else:
            console.print(f"[yellow]![/yellow] Backend returned status {response.status_code}")
            return False
    except Exception as e:
        console.print(f"[red]✗[/red] Backend health check failed: {e}")
        return False


def main():
    """Run full deployment process."""
    console.print("[bold blue]Trail Full Deployment[/bold blue]\n")
    
    # Step 0: Prepare deployment (composer install)
    if not run_script("prepare_deploy", "Step 0: Build Dependencies"):
        console.print("[red]Deployment failed at dependency build[/red]")
        sys.exit(1)
    
    # Step 1: Run database migrations
    if not run_script("db_migrate", "Step 1: Database Migrations"):
        console.print("[red]Deployment failed at database migrations[/red]")
        sys.exit(1)
    
    # Step 2: Deploy PHP files
    if not run_script("deploy", "Step 2: FTP Deployment"):
        console.print("[red]Deployment failed at FTP upload[/red]")
        sys.exit(1)
    
    # Step 3: Verify backend health
    console.print("\n[bold blue]Step 3: Backend Health Check[/bold blue]")
    console.print("=" * 60)
    
    # Load config for health check
    try:
        from deploy import load_config
        config = load_config()
        
        if verify_backend_health(config):
            console.print("\n[bold green]Deployment completed successfully![/bold green]")
        else:
            console.print("\n[yellow]Deployment completed but health check failed[/yellow]")
            console.print("[yellow]Please verify backend manually[/yellow]")
    except Exception as e:
        console.print(f"\n[yellow]Could not verify backend health: {e}[/yellow]")
        console.print("[yellow]Please verify backend manually[/yellow]")


if __name__ == "__main__":
    main()
