#!/usr/bin/env -S uv run python
"""FTP deployment script for Trail service."""

import os
import sys
from pathlib import Path
from ftplib import FTP, error_perm
import yaml
from rich.console import Console
from rich.progress import Progress, BarColumn, TextColumn, TimeRemainingColumn
from dotenv import load_dotenv

console = Console()


def load_config():
    """Load configuration from config.yml with environment variable substitution."""
    config_path = Path(__file__).parent.parent / "config.yml"
    
    if not config_path.exists():
        console.print(f"[red]Error: Configuration file not found at {config_path}[/red]")
        sys.exit(1)
    
    # Load environment variables
    env_path = Path(__file__).parent.parent / ".env"
    if env_path.exists():
        load_dotenv(env_path)
    
    with open(config_path) as f:
        content = f.read()
    
    # Replace environment variables
    import re
    def replace_env(match):
        env_var = match.group(1)
        value = os.getenv(env_var)
        if value is None:
            console.print(f"[red]Error: Environment variable {env_var} is not set[/red]")
            sys.exit(1)
        return value
    
    content = re.sub(r'\$\{([A-Z_]+)\}', replace_env, content)
    
    return yaml.safe_load(content)


def get_files_to_upload():
    """Get list of files to upload (exclude certain directories).
    
    NOTE: vendor/ directory IS uploaded because production server has no Composer.
    Run 'composer install' locally/Docker before deployment to ensure vendor/ is up to date.
    """
    backend_dir = Path(__file__).parent.parent / "backend"
    
    if not backend_dir.exists():
        console.print(f"[red]Error: Backend directory not found at {backend_dir}[/red]")
        sys.exit(1)
    
    exclude_patterns = {
        '.git', '.gitignore', 'tests', 'node_modules',
        '.env', '.DS_Store', 'docker-compose.yml', 'Dockerfile',
        'phpunit.xml', '.phpunit.cache', 'docker'
    }
    
    files_to_upload = []
    
    for file_path in backend_dir.rglob('*'):
        if file_path.is_file():
            # Check if any part of the path matches exclude patterns
            if not any(pattern in file_path.parts for pattern in exclude_patterns):
                relative_path = file_path.relative_to(backend_dir)
                files_to_upload.append((file_path, relative_path))
    
    return files_to_upload


def ensure_remote_directory(ftp, remote_path):
    """Ensure remote directory exists, create if it doesn't."""
    dirs = remote_path.split('/')
    current_path = ''
    
    for dir_name in dirs:
        if not dir_name:
            continue
        
        current_path += '/' + dir_name
        
        try:
            ftp.cwd(current_path)
        except error_perm:
            try:
                ftp.mkd(current_path)
                ftp.cwd(current_path)
            except error_perm as e:
                console.print(f"[red]Error creating directory {current_path}: {e}[/red]")


def upload_file(ftp, local_file, remote_file):
    """Upload a single file via FTP."""
    with open(local_file, 'rb') as f:
        ftp.storbinary(f'STOR {remote_file}', f)


def check_vendor_directory():
    """Verify vendor directory exists and warn if it seems outdated."""
    backend_dir = Path(__file__).parent.parent / "backend"
    vendor_dir = backend_dir / "vendor"
    composer_lock = backend_dir / "composer.lock"
    
    if not vendor_dir.exists():
        console.print("[red]✗ Error: vendor/ directory not found![/red]")
        console.print("[yellow]Run 'composer install' in the backend directory first.[/yellow]")
        sys.exit(1)
    
    # Check if vendor is older than composer.lock
    if composer_lock.exists():
        vendor_autoload = vendor_dir / "autoload.php"
        if vendor_autoload.exists():
            if composer_lock.stat().st_mtime > vendor_autoload.stat().st_mtime:
                console.print("[yellow]⚠ Warning: composer.lock is newer than vendor/[/yellow]")
                console.print("[yellow]Consider running 'composer install' to update dependencies.[/yellow]")
                response = input("Continue anyway? (y/N): ")
                if response.lower() != 'y':
                    sys.exit(0)
    
    console.print(f"[green]✓[/green] vendor/ directory found")


def main():
    """Deploy PHP files to FTP server."""
    console.print("[bold blue]Trail FTP Deployment Tool[/bold blue]\n")
    
    # Check vendor directory exists
    check_vendor_directory()
    
    # Load configuration
    config = load_config()
    console.print(f"[green]✓[/green] Configuration loaded")
    
    # Get files to upload
    files_to_upload = get_files_to_upload()
    console.print(f"[green]✓[/green] Found {len(files_to_upload)} files to upload\n")
    
    # Connect to FTP
    try:
        ftp = FTP()
        ftp.connect(config['ftp']['host'], config['ftp']['port'])
        ftp.login(config['ftp']['user'], config['ftp']['password'])
        console.print(f"[green]✓[/green] Connected to FTP server: {config['ftp']['host']}")
    except Exception as e:
        console.print(f"[red]Error connecting to FTP server: {e}[/red]")
        sys.exit(1)
    
    try:
        # Change to remote path
        remote_base = config['ftp']['remote_path']
        ensure_remote_directory(ftp, remote_base)
        ftp.cwd(remote_base)
        console.print(f"[green]✓[/green] Changed to remote directory: {remote_base}\n")
        
        # Upload files
        console.print("[yellow]Uploading files...[/yellow]\n")
        
        with Progress(
            TextColumn("[progress.description]{task.description}"),
            BarColumn(),
            TextColumn("[progress.percentage]{task.percentage:>3.0f}%"),
            TimeRemainingColumn(),
            console=console
        ) as progress:
            task = progress.add_task("Uploading...", total=len(files_to_upload))
            
            for local_file, relative_path in files_to_upload:
                # Ensure remote directory exists
                remote_dir = str(relative_path.parent)
                if remote_dir and remote_dir != '.':
                    ensure_remote_directory(ftp, remote_dir)
                    ftp.cwd(remote_base)
                
                # Upload file
                remote_file = str(relative_path).replace('\\', '/')
                try:
                    upload_file(ftp, local_file, remote_file)
                    progress.update(task, advance=1, description=f"Uploaded {relative_path}")
                except Exception as e:
                    progress.update(task, advance=1, description=f"[red]Failed {relative_path}[/red]")
                    console.print(f"\n[red]Error uploading {relative_path}: {e}[/red]")
        
        console.print(f"\n[bold green]Successfully uploaded {len(files_to_upload)} files![/bold green]")
        
    finally:
        ftp.quit()


if __name__ == "__main__":
    main()
