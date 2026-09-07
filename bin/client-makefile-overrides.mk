# === Client overrides ===
# These targets replace aurora's defaults with client-specific behaviour.

# AURORA_CLIENT_DIR is read by aurora's vite.config.js and app.js to expose
# this client's custom Vue components (and Stimulus controllers, CSS) via the
# @client alias. Place client modules in assets/client/Module/<Name>/...
# NODE_PATH lets Node resolve packages (vue, vue-i18n, …) from aurora's
# node_modules even when the importing file lives outside vendor/aurora.
CLIENT_ASSETS = $(CURDIR)/assets/client
AURORA_NODE   = $(CURDIR)/$(AURORA)/node_modules
AURORA_ENV    = AURORA_CLIENT_DIR=$(CLIENT_ASSETS) NODE_PATH=$(AURORA_NODE)

install: install-dev ## Install the project (alias for install-dev)

fixtures: ## Drop DB, re-run migrations, load fixtures and sync all
	$(CONSOLE) doctrine:database:drop --force --if-exists
	$(CONSOLE) doctrine:database:create --if-not-exists
	$(CONSOLE) doctrine:migrations:migrate --no-interaction
	$(CONSOLE) doctrine:fixtures:load --no-interaction
	$(CONSOLE) aurora:application-parameter
	$(CONSOLE) aurora:menus:sync
	$(CONSOLE) aurora:privileges:sync
	@echo "✅ Fixtures loaded"

demo: ## Load demo fixtures (DemoFixtures group) + run all syncs
	$(CONSOLE) doctrine:fixtures:load --group=demo --no-interaction
	$(CONSOLE) aurora:application-parameter
	$(CONSOLE) aurora:menus:sync
	$(CONSOLE) aurora:privileges:sync
	@echo "✅ Demo data loaded"

stop: ## Stop dev server and kill Vite
	symfony server:stop
	@pkill -f "pnpm.*$(AURORA).*dev" 2>/dev/null || true
	@docker compose stop database 2>/dev/null || true

start: ## Start dev server + Vite dev server
	@docker compose up -d database 2>/dev/null || true
	symfony server:start -d
	@[ -d "$(AURORA)/vendor" ] || $(COMPOSER) install --working-dir=$(AURORA) --no-scripts
	@[ -d "$(AURORA)/node_modules" ] || $(PNPM) --dir=$(AURORA) install
	$(AURORA_ENV) $(PNPM) --dir=$(AURORA) run dev

build: ## Build assets for production
	$(AURORA_ENV) $(PNPM) --dir=$(AURORA) run build

dev: ## Start Vite dev server
	$(AURORA_ENV) $(PNPM) --dir=$(AURORA) run dev

install-dev: ## Install for local development
	$(COMPOSER) install --no-scripts
	$(COMPOSER) install --working-dir=$(AURORA) --no-scripts
	$(COMPOSER) install --working-dir=$(AURORA)/tools/php-cs-fixer
	$(COMPOSER) install --working-dir=$(AURORA)/tools/twig-cs-fixer
	$(COMPOSER) install --working-dir=$(AURORA)/tools/rector
	$(COMPOSER) install --working-dir=$(AURORA)/tools/phpstan
	$(PNPM) --dir=$(AURORA) install
	$(PNPM) install
	@ln -sf ../$(AURORA)/public/build public/build
	make setup-dirs
	make db-create
	make migrate
	$(CONSOLE) doctrine:fixtures:load --no-interaction
	$(CONSOLE) aurora:application-parameter
	$(CONSOLE) aurora:menus:sync
	$(CONSOLE) aurora:privileges:sync
	make sync-jsconfig
	make dev
	@echo "✅ Admin user: admin@aurora.app / password"

install-prod: ## Install for production
	$(COMPOSER) install --no-dev --optimize-autoloader
	$(PNPM) --dir=$(AURORA) install --frozen-lockfile
	make setup-dirs
	make migrate-f
	$(CONSOLE) aurora:application-parameter
	$(CONSOLE) aurora:menus:sync
	$(CONSOLE) aurora:privileges:sync
	make build
	make cc-prod

# The cache is rebuilt twice on purpose - see the client template's Makefile:
# a console command run against a container compiled from the previous release
# fails on any constructor that has gained an argument, before a single
# migration has run.
deploy-prod: ## Deploy to production (requires a git tag on HEAD)
	@APP_VERSION=$$(git describe --exact-match --tags HEAD 2>/dev/null); \
	if [ -z "$$APP_VERSION" ]; then \
		echo "❌ HEAD has no exact git tag. Run: make tag VERSION=x.y.z"; \
		exit 1; \
	fi; \
	echo "🚀 Deploying version $$APP_VERSION..."; \
	echo "$$APP_VERSION" > VERSION; \
	$(COMPOSER) install --no-dev --optimize-autoloader; \
	$(PNPM) --dir=$(AURORA) install --frozen-lockfile; \
	APP_ENV=prod APP_DEBUG=0 $(CONSOLE) cache:clear --env=prod; \
	$(CONSOLE) doctrine:migrations:migrate --no-interaction; \
	$(CONSOLE) aurora:application-parameter; \
	$(CONSOLE) aurora:menus:sync; \
	$(CONSOLE) aurora:privileges:sync; \
	$(AURORA_ENV) $(PNPM) --dir=$(AURORA) run build; \
	APP_ENV=prod APP_DEBUG=0 $(CONSOLE) cache:clear --env=prod; \
	echo "✅ Deployed $$APP_VERSION"

sync-jsconfig: ## Regenerate jsconfig.json from aurora module aliases (run after aurora-update)
	node $(AURORA)/bin/sync-client-jsconfig

check-env-drift: ## Warn about env vars in Aurora's .env not present in client env files
	@aurora_vars=$$(grep -v '^#' $(AURORA)/.env | grep -v '^$$' | sed 's/=.*//' | sort); \
	client_vars=$$(cat .env .env.local.example .env.test 2>/dev/null | grep -v '^#' | grep -v '^$$' | sed 's/=.*//' | sort -u); \
	missing=$$(comm -23 <(echo "$$aurora_vars") <(echo "$$client_vars")); \
	if [ -n "$$missing" ]; then \
		echo ""; \
		echo "⚠️  New Aurora env vars not declared in your .env / .env.local.example:"; \
		echo "$$missing" | sed 's/^/   - /'; \
		echo ""; \
		echo "   → Add them to .env (placeholder) and .env.local (real value)."; \
		echo ""; \
	fi

aurora-update: ## Pull latest Aurora changes
	$(COMPOSER) update axelraboit/aurora
	$(COMPOSER) install --working-dir=$(AURORA) --no-scripts
	$(COMPOSER) install --working-dir=$(AURORA)/tools/php-cs-fixer
	$(COMPOSER) install --working-dir=$(AURORA)/tools/twig-cs-fixer
	$(COMPOSER) install --working-dir=$(AURORA)/tools/rector
	$(COMPOSER) install --working-dir=$(AURORA)/tools/phpstan
	$(PNPM) --dir=$(AURORA) install
	$(PNPM) install
	make migrate
	$(CONSOLE) aurora:privileges:sync
	make sync-jsconfig
	make check-env-drift
	@echo "✅ Aurora updated"
