test:
	phpunit
gendoc:
	cd doc && make html
genapi:
	mkdir -p build/api
	apigen generate -s src -d build/api
ghpage: clean genapi
	mkdir -p build/ghpage
	cd build/ghpage && git init
	cp -R build/api/* build/ghpage
	cd build/ghpage && git add --all && git commit -m "github pages" && \
	git push --force git@github.com:arkphp/database.git master:gh-pages
clean:
	rm -rf build