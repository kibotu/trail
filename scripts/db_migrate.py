#!/usr/bin/env -S uv run python
"""Database migration script for Trail service."""

import os
import sys
from pathlib import Path
import yaml
import mysql.connector
from mysql.connector import Error
from rich.console import Console
from rich.progress import Progress, SpinnerColumn, TextColumn
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


def get_db_connection(config):
    """Create database connection."""
    try:
        connection = mysql.connector.connect(
            host=config['database']['host'],
            port=config['database']['port'],
            user=config['database']['user'],
            password=config['database']['password'],
            database=config['database']['name']
        )
        return connection
    except Error as e:
        console.print(f"[red]Error connecting to MySQL: {e}[/red]")
        sys.exit(1)


def get_applied_migrations(cursor):
    """Get list of already applied migrations."""
    cursor.execute("""
        SELECT migration_name FROM trail_migrations 
        ORDER BY applied_at
    """)
    return {row[0] for row in cursor.fetchall()}


def get_migration_files():
    """Get list of migration SQL files."""
    migrations_dir = Path(__file__).parent.parent / "migrations"
    
    if not migrations_dir.exists():
        console.print(f"[red]Error: Migrations directory not found at {migrations_dir}[/red]")
        sys.exit(1)
    
    migration_files = sorted(migrations_dir.glob("*.sql"))
    return migration_files


def apply_migration(cursor, migration_file):
    """Apply a single migration file."""
    with open(migration_file) as f:
        sql_content = f.read()
    
    # Split by semicolon and execute each statement
    statements = [s.strip() for s in sql_content.split(';') if s.strip()]
    
    for statement in statements:
        if statement:
            cursor.execute(statement)


def main():
    """Run database migrations."""
    console.print("[bold blue]Trail Database Migration Tool[/bold blue]\n")
    
    # Load configuration
    config = load_config()
    console.print(f"[green]✓[/green] Configuration loaded")
    
    # Connect to database
    connection = get_db_connection(config)
    cursor = connection.cursor()
    console.print(f"[green]✓[/green] Connected to database: {config['database']['name']}")
    
    try:
        # Get applied migrations
        try:
            applied = get_applied_migrations(cursor)
            console.print(f"[green]✓[/green] Found {len(applied)} applied migrations")
        except Error:
            # Migrations table doesn't exist yet
            applied = set()
            console.print("[yellow]![/yellow] Migrations table not found, will be created")
        
        # Get migration files
        migration_files = get_migration_files()
        console.print(f"[green]✓[/green] Found {len(migration_files)} migration files\n")
        
        # Apply pending migrations
        pending = [f for f in migration_files if f.name not in applied]
        
        if not pending:
            console.print("[green]All migrations are up to date![/green]")
            return
        
        console.print(f"[yellow]Applying {len(pending)} pending migration(s):[/yellow]\n")
        
        with Progress(
            SpinnerColumn(),
            TextColumn("[progress.description]{task.description}"),
            console=console
        ) as progress:
            for migration_file in pending:
                task = progress.add_task(f"Applying {migration_file.name}...", total=None)
                
                try:
                    apply_migration(cursor, migration_file)
                    connection.commit()
                    progress.update(task, description=f"[green]✓[/green] Applied {migration_file.name}")
                except Error as e:
                    progress.update(task, description=f"[red]✗[/red] Failed {migration_file.name}")
                    console.print(f"\n[red]Error applying migration {migration_file.name}:[/red]")
                    console.print(f"[red]{e}[/red]")
                    connection.rollback()
                    sys.exit(1)
        
        console.print(f"\n[bold green]Successfully applied {len(pending)} migration(s)![/bold green]")
        
    finally:
        cursor.close()
        connection.close()


if __name__ == "__main__":
    main()
