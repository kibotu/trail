# Makefile for Trail Docker Operations
# Modern Docker best practices (2026)

.PHONY: help setup up down restart logs ps build clean test shell db-shell secrets prod

# Default target
.DEFAULT_GOAL := help

# Colors for output
BLUE := \033[0;34m
GREEN := \033[0;32m
YELLOW := \033[0;33m
RED := \033[0;31m
NC := \033[0m # No Color

help: ## Show this help message
	@echo "$(BLUE)Trail Docker Commands$(NC)"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  $(GREEN)%-15s$(NC) %s\n", $$1, $$2}'
	@echo ""

setup: ## Initial setup - create symlink and validate .env
	@echo "$(BLUE)Setting up Docker environment...$(NC)"
	@if [ ! -L .env ]; then \
		echo "Creating symlink to ../.env"; \
		ln -sf ../.env .env; \
	fi
	@./setup-docker.sh
	@echo "$(GREEN)✓ Setup complete!$(NC)"

validate: setup ## Alias for setup

up: ## Start all services (development)
	@echo "$(BLUE)Starting services...$(NC)"
	@docker compose up -d
	@echo "$(GREEN)✓ Services started!$(NC)"
	@echo "$(YELLOW)Waiting for services to be healthy...$(NC)"
	@sleep 5
	@docker compose ps

down: ## Stop all services
	@echo "$(BLUE)Stopping services...$(NC)"
	@docker compose down
	@echo "$(GREEN)✓ Services stopped!$(NC)"

restart: ## Restart all services
	@echo "$(BLUE)Restarting services...$(NC)"
	@docker compose restart
	@echo "$(GREEN)✓ Services restarted!$(NC)"

logs: ## Show logs (follow mode)
	@docker compose logs -f

ps: ## Show service status
	@docker compose ps

build: ## Build/rebuild images
	@echo "$(BLUE)Building images...$(NC)"
	@docker compose build --pull
	@echo "$(GREEN)✓ Build complete!$(NC)"

rebuild: ## Rebuild images without cache
	@echo "$(BLUE)Rebuilding images without cache...$(NC)"
	@docker compose build --no-cache --pull
	@echo "$(GREEN)✓ Rebuild complete!$(NC)"

clean: ## Remove containers, volumes, and images
	@echo "$(RED)Removing all containers, volumes, and images...$(NC)"
	@docker compose down -v --rmi all
	@echo "$(GREEN)✓ Cleanup complete!$(NC)"

test: ## Run PHP tests
	@echo "$(BLUE)Running tests...$(NC)"
	@docker compose exec web composer test

shell: ## Open shell in web container
	@docker compose exec web bash

db-shell: ## Open MariaDB shell
	@docker compose exec db mysql -u trail_user -p trail_db

db-root: ## Open MariaDB shell as root
	@docker compose exec db mysql -u root -p

health: ## Check health status of all services
	@echo "$(BLUE)Service Health Status:$(NC)"
	@docker compose ps
	@echo ""
	@echo "$(BLUE)Detailed Health Checks:$(NC)"
	@docker inspect trail-web --format='Web: {{.State.Health.Status}}' 2>/dev/null || echo "Web: not running"
	@docker inspect trail-db --format='Database: {{.State.Health.Status}}' 2>/dev/null || echo "Database: not running"
	@docker inspect trail-pma --format='phpMyAdmin: {{.State.Health.Status}}' 2>/dev/null || echo "phpMyAdmin: not running"

stats: ## Show resource usage
	@docker stats --no-stream

# Production targets
prod-build: ## Build production images
	@echo "$(BLUE)Building production images...$(NC)"
	@docker compose -f docker-compose.yml -f docker-compose.prod.yml build
	@echo "$(GREEN)✓ Production build complete!$(NC)"

prod-up: ## Start production stack
	@echo "$(BLUE)Starting production services...$(NC)"
	@docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
	@echo "$(GREEN)✓ Production services started!$(NC)"
	@sleep 5
	@docker compose ps

prod-down: ## Stop production stack
	@echo "$(BLUE)Stopping production services...$(NC)"
	@docker compose -f docker-compose.yml -f docker-compose.prod.yml down
	@echo "$(GREEN)✓ Production services stopped!$(NC)"

prod-logs: ## Show production logs
	@docker compose -f docker-compose.yml -f docker-compose.prod.yml logs -f

# Database operations
db-backup: ## Backup database to backups/
	@mkdir -p backups
	@echo "$(BLUE)Backing up database...$(NC)"
	@docker compose exec db mysqldump -u root -p trail_db > backups/backup_$$(date +%Y%m%d_%H%M%S).sql
	@echo "$(GREEN)✓ Backup complete!$(NC)"

db-restore: ## Restore database from latest backup
	@echo "$(BLUE)Restoring database from latest backup...$(NC)"
	@docker compose exec -T db mysql -u root -p trail_db < $$(ls -t backups/*.sql | head -1)
	@echo "$(GREEN)✓ Restore complete!$(NC)"

# Maintenance
prune: ## Remove unused Docker resources
	@echo "$(YELLOW)Removing unused Docker resources...$(NC)"
	@docker system prune -f
	@echo "$(GREEN)✓ Prune complete!$(NC)"

prune-all: ## Remove ALL unused Docker resources (including volumes)
	@echo "$(RED)Removing ALL unused Docker resources...$(NC)"
	@docker system prune -af --volumes
	@echo "$(GREEN)✓ Deep prune complete!$(NC)"

update: ## Pull latest images and restart
	@echo "$(BLUE)Pulling latest images...$(NC)"
	@docker compose pull
	@echo "$(BLUE)Restarting services...$(NC)"
	@docker compose up -d
	@echo "$(GREEN)✓ Update complete!$(NC)"

# Development helpers
composer-install: ## Install PHP dependencies
	@docker compose exec web composer install

composer-update: ## Update PHP dependencies
	@docker compose exec web composer update

composer-dump: ## Regenerate autoloader
	@docker compose exec web composer dump-autoload

# Quick access URLs
urls: ## Show service URLs
	@echo "$(BLUE)Service URLs:$(NC)"
	@echo "  Backend API:  $(GREEN)http://localhost:18000$(NC)"
	@echo "  Admin Panel:  $(GREEN)http://localhost:18000/admin$(NC)"
	@echo "  phpMyAdmin:   $(GREEN)http://localhost:18080$(NC)"
	@echo "  MariaDB:      $(GREEN)localhost:13306$(NC)"
	@echo ""
	@echo "$(YELLOW)Note: Non-standard ports avoid conflicts with other services$(NC)"

# Info
version: ## Show Docker and Compose versions
	@echo "$(BLUE)Docker Version:$(NC)"
	@docker version --format '  Client: {{.Client.Version}}\n  Server: {{.Server.Version}}\n  API:    {{.Server.ApiVersion}}'
	@echo ""
	@echo "$(BLUE)Docker Compose Version:$(NC)"
	@docker compose version

info: version urls ## Show version and URLs
