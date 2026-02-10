#!/usr/bin/env -S uv run python
"""Installation script for Trail service."""

import os
import sys
import subprocess
import shutil
from pathlib import Path
from rich.console import Console
from rich.prompt import Prompt, Confirm
from rich.panel import Panel

console = Console()


def check_prerequisites():
    """Check if required tools are installed."""
    console.print("[bold blue]Checking Prerequisites[/bold blue]\n")
    
    prerequisites = {
        'python': ('python3', '--version'),
        'uv': ('uv', '--version'),
        'composer': ('composer', '--version'),
    }
    
    missing = []
    
    for name, (command, *args) in prerequisites.items():
        try:
            result = subprocess.run(
                [command] + list(args),
                capture_output=True,
                text=True,
                check=True
            )
            version = result.stdout.strip().split('\n')[0]
            console.print(f"[green]✓[/green] {name}: {version}")
        except (subprocess.CalledProcessError, FileNotFoundError):
            console.print(f"[red]✗[/red] {name}: Not found")
            missing.append(name)
    
    if missing:
        console.print(f"\n[red]Missing prerequisites: {', '.join(missing)}[/red]")
        console.print("[yellow]Please install missing tools and try again[/yellow]")
        return False
    
    console.print("\n[green]All prerequisites are installed![/green]\n")
    return True


def generate_config():
    """Generate configuration files interactively."""
    console.print("[bold blue]Configuration Setup[/bold blue]\n")
    
    # Database configuration
    console.print("[yellow]Database Configuration:[/yellow]")
    db_host = Prompt.ask("Database host", default="localhost")
    db_name = Prompt.ask("Database name", default="trail_db")
    db_user = Prompt.ask("Database user", default="trail_user")
    db_password = Prompt.ask("Database password", password=True)
    
    # FTP configuration
    console.print("\n[yellow]FTP Configuration:[/yellow]")
    ftp_host = Prompt.ask("FTP host", default="ftp.example.com")
    ftp_user = Prompt.ask("FTP username")
    ftp_password = Prompt.ask("FTP password", password=True)
    ftp_path = Prompt.ask("FTP remote path", default="/public_html/trail")
    
    # Google OAuth configuration
    console.print("\n[yellow]Google OAuth Configuration:[/yellow]")
    google_client_id = Prompt.ask("Google OAuth Client ID")
    google_client_secret = Prompt.ask("Google OAuth Client Secret", password=True)
    
    # JWT configuration
    console.print("\n[yellow]JWT Configuration:[/yellow]")
    import secrets
    jwt_secret = secrets.token_urlsafe(32)
    console.print(f"Generated JWT secret: {jwt_secret[:20]}...")
    
    # App configuration
    console.print("\n[yellow]Application Configuration:[/yellow]")
    base_url = Prompt.ask("Application base URL", default="https://example.com/trail")
    
    # Create .env file
    env_content = f"""# Database
DB_HOST={db_host}
DB_NAME={db_name}
DB_USER={db_user}
DB_PASSWORD={db_password}

# FTP
FTP_USER={ftp_user}
FTP_PASSWORD={ftp_password}

# Google OAuth
GOOGLE_CLIENT_ID={google_client_id}
GOOGLE_CLIENT_SECRET={google_client_secret}

# JWT
JWT_SECRET={jwt_secret}
"""
    
    env_path = Path(__file__).parent.parent / ".env"
    with open(env_path, 'w') as f:
        f.write(env_content)
    
    console.print(f"\n[green]✓[/green] Created .env file at {env_path}")
    
    # Create config.yml
    config_content = f"""database:
  host: ${{DB_HOST}}
  port: 3306
  name: ${{DB_NAME}}
  user: ${{DB_USER}}
  password: ${{DB_PASSWORD}}
  prefix: trail_
  charset: utf8mb4
  collation: utf8mb4_unicode_ci

ftp:
  host: {ftp_host}
  port: 21
  user: ${{FTP_USER}}
  password: ${{FTP_PASSWORD}}
  remote_path: {ftp_path}

google_oauth:
  client_id: ${{GOOGLE_CLIENT_ID}}
  client_secret: ${{GOOGLE_CLIENT_SECRET}}

jwt:
  secret: ${{JWT_SECRET}}
  expiry_hours: 168

security:
  rate_limit:
    enabled: true
    requests_per_minute: 60
    requests_per_hour: 1000
  bot_protection:
    enabled: true
    require_user_agent: true
    block_suspicious_patterns: true

app:
  base_url: {base_url}
  rss_title: "Trail - Link Journal"
  rss_description: "Public link journal feed"
  environment: production
"""
    
    config_path = Path(__file__).parent.parent / "config.yml"
    with open(config_path, 'w') as f:
        f.write(config_content)
    
    console.print(f"[green]✓[/green] Created config.yml at {config_path}\n")


def install_backend_dependencies():
    """Install PHP backend dependencies."""
    console.print("[bold blue]Installing Backend Dependencies[/bold blue]\n")
    
    backend_dir = Path(__file__).parent.parent / "backend"
    
    try:
        subprocess.run(
            ["composer", "install", "--no-dev", "--optimize-autoloader"],
            cwd=backend_dir,
            check=True
        )
        console.print("[green]✓[/green] Backend dependencies installed\n")
        return True
    except subprocess.CalledProcessError as e:
        console.print(f"[red]✗[/red] Failed to install backend dependencies: {e}\n")
        return False


def run_migrations():
    """Run database migrations."""
    console.print("[bold blue]Running Database Migrations[/bold blue]\n")
    
    if Confirm.ask("Run database migrations?"):
        try:
            subprocess.run(
                ["uv", "run", "python", "db_migrate.py"],
                cwd=Path(__file__).parent,
                check=True
            )
            console.print("[green]✓[/green] Database migrations completed\n")
            return True
        except subprocess.CalledProcessError as e:
            console.print(f"[red]✗[/red] Failed to run migrations: {e}\n")
            return False
    else:
        console.print("[yellow]Skipped database migrations[/yellow]\n")
        return True


def main():
    """Run installation process."""
    console.print(Panel.fit(
        "[bold blue]Trail Service Installer[/bold blue]\n\n"
        "This script will help you set up the Trail service.",
        border_style="blue"
    ))
    console.print()
    
    # Check prerequisites
    if not check_prerequisites():
        sys.exit(1)
    
    # Generate configuration
    generate_config()
    
    # Install backend dependencies
    install_backend_dependencies()
    
    # Run migrations
    run_migrations()
    
    # Display next steps
    console.print(Panel.fit(
        "[bold green]Installation Complete![/bold green]\n\n"
        "Next steps:\n"
        "1. Configure google-services.json for Android app\n"
        "2. Update API base URL in Android app (di/KoinModules.kt)\n"
        "3. Build Android app: cd android && ./gradlew assembleDebug\n\n"
        "For production deployment, run: uv run python scripts/full_deploy.py",
        border_style="green"
    ))


if __name__ == "__main__":
    main()
