DEPENDENCY_TARGETS += cotor

cotor.phar: box.json $(shell find src -type f) vendor
	box compile

cotor: cotor.phar
	@cp -a -v cotor.phar cotor

.PHONY: phar
phar: ## Create cotor.phar
phar: cotor
	@true
