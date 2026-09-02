# Test environment for the WordPress integration suite.
#
# WHY THIS EXISTS: the WordPress test library is checked out with svn, and
# install-wp-tests.sh creates its database with mysqladmin. Neither is present
# on the Windows host (measured 2026-09-02), and neither belongs there.
#
# WHY IT IS NOT A PRODUCT STACK: this package is infrastructure -- every MHM
# plugin vendors it. Binding its test run to one product's dev stack would make
# that product's lifecycle a dependency of this package's gate. The same
# argument produced mhm-svn:1 in wp-knowledge/docker-svn.
#
# The SERVER is mysql:8.0, the same image CI runs -- that is the half that
# decides engine semantics. The CLIENT is Debian's default, which is MariaDB's;
# it only has to create a database. It refuses mysql:8.0's self-signed
# certificate out of the box -- measured: "TLS/SSL error: self-signed
# certificate in certificate chain" -- so TLS is disabled for the client HERE,
# in the image. Deliberately NOT in install-wp-tests.sh: that script has to
# stay identical to the one CI runs, or the local run stops answering the same
# question as the pipeline.

FROM php:8.2-cli

RUN apt-get update \
	&& apt-get install -y --no-install-recommends \
		subversion \
		default-mysql-client \
		unzip \
		ca-certificates \
	&& docker-php-ext-install mysqli \
	&& rm -rf /var/lib/apt/lists/*

RUN mkdir -p /etc/mysql/conf.d \
	&& { echo '[client]'; echo 'skip-ssl'; } > /etc/mysql/conf.d/skip-ssl.cnf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /pkg
