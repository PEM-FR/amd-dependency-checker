Amd Dependency Checker
======================
@author PEM
Disclaimer : This file is ugly, incomplete, basic...
and I know all of that already.
If you don't like it don't use it or wait for a cleaner version if any... 

kthxbye :)


Args should be written like that :
---------------------
`php amd-dependency-checker.php path=some/path recursive verbose unused`

or

`php amd-dependency-checker.php path=some/path recursive verbose unused > someFile` if you want to output everything in a file

*  path=string default to current path
*  recursive if not set then false
*  verbose if not set then false
*  unused if not set then false


of course you can use 
---------------------
*  `php amd-dependency-checker.php help`
*  `php amd-dependency-checker.php path help`
*  `php amd-dependency-checker.php recursive help`
*  `php amd-dependency-checker.php verbose help`
*  `php amd-dependency-checker.php unused help`
