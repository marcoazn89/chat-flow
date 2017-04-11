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
	docker run -tid -p 80:80 -v <full path to root of the project>:/var/www/html --name chatflow chatflow
	Load this url http://localhost/test/test.php
