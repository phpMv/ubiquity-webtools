![img](https://github.com/phpmv/ubiquity-webtools/blob/master/.github/images/webtools.png?raw=true)

[![Latest Stable Version](https://poser.pugx.org/phpmv/ubiquity-webtools/v/stable)](https://packagist.org/packages/phpmv/ubiquity-webtools)
[![Total Downloads](https://poser.pugx.org/phpmv/ubiquity-webtools/downloads)](https://packagist.org/packages/phpmv/ubiquity-webtools)
[![Latest Unstable Version](https://poser.pugx.org/phpmv/ubiquity-webtools/v/unstable)](https://packagist.org/packages/phpmv/ubiquity-webtools)
[![License](https://poser.pugx.org/phpmv/ubiquity-devtools/license)](https://packagist.org/packages/phpmv/ubiquity-webtools)
[![Documentation Status](https://readthedocs.org/projects/micro-framework/badge/?version=latest)](http://micro-framework.readthedocs.io/en/latest/?badge=latest)

Web tools for [Ubiquity framework](https://github.com/phpMv/ubiquity)
## I - Installation
Webtools are installed from the devtools.

### At the project creation
With the `-a`option:
```bash
Ubiquity new projectName -a
```
### With an existing project
if the project is older than Ubiquity version 2.1.5,
update devtools:
```bash
composer global update
```
In your Ubiquity project folder:

```bash
Ubiquity admin
```

To confirm **Ubiquity webtools** was successfully installed,

type ``Ubiquity version``:

![img](https://github.com/phpmv/ubiquity-webtools/blob/master/.github/images/webtools-version.png)

## II - Running

Start the embedded web server:

``Ubiquity serve``

Go to the address: ``http://127.0.0.1:8090/Admin``

![img](https://github.com/phpmv/ubiquity-webtools/blob/master/.github/images/webtools-interface.png)
