# ChatFlow
State manager for chat bots

Requirements
------------
* php 7.1+

When cloning the repo, run these at the root level
-------------------------------------------------------------
	composer install

Test with Docker
-----------------
	cd docker
	docker run -tid -p 80:80 -v c:/xampp/htdocs/chat-flow/:/var/www/html --name chatflow chatflow
