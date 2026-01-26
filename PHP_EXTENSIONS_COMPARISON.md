# PHP Extensions Comparison

## Production Server (from phpinfo)

**PHP Version**: 8.4.16-nmm1  
**Server API**: FPM/FastCGI  
**System**: Linux (Ubuntu)

### Loaded Extensions on Production

#### Core Extensions (Always Available)
- Core
- date
- filter
- hash
- json
- libxml
- pcre
- random
- Reflection
- session
- SPL
- standard
- tokenizer

#### Database Extensions
- ✅ **mysqli** - MySQL Improved (required)
- ✅ **mysqlnd** - MySQL Native Driver (required)
- ✅ **pdo** - PHP Data Objects (required)
- ✅ **pdo_mysql** - PDO MySQL Driver (required)
- pdo_dblib - MS SQL
- pdo_firebird - Firebird
- pdo_odbc - ODBC
- pdo_pgsql - PostgreSQL
- pdo_sqlite - SQLite
- pgsql - PostgreSQL
- sqlite3 - SQLite3
- odbc - ODBC

#### String/Text Extensions
- ✅ **mbstring** - Multibyte String (required)
- ✅ **iconv** - Character encoding conversion
- gettext - Internationalization

#### XML Extensions
- ✅ **dom** - DOM manipulation
- ✅ **xml** - XML parser
- ✅ **SimpleXML** - Simple XML
- xmlreader - XML Reader
- xmlwriter - XML Writer
- xsl - XSL transformation

#### Image Extensions
- ✅ **gd** - Image manipulation (required)
- ✅ **exif** - EXIF data from images (required)
- imagick - ImageMagick (bonus, not required)

#### Compression Extensions
- ✅ **zip** - ZIP archive (required)
- ✅ **zlib** - Compression
- bz2 - BZip2 compression

#### Math Extensions
- ✅ **bcmath** - Arbitrary precision math (required)
- gmp - GNU Multiple Precision

#### Internationalization
- ✅ **intl** - Internationalization (required)
- calendar - Calendar functions

#### Network/Protocol Extensions
- ✅ **curl** - cURL (required for Google API)
- ✅ **openssl** - OpenSSL (required for HTTPS)
- ✅ **ftp** - FTP
- soap - SOAP protocol
- imap - IMAP/POP3

#### Security/Encoding
- ✅ **sodium** - Modern cryptography
- ✅ **openssl** - SSL/TLS

#### Other Extensions
- ✅ **ctype** - Character type checking
- ✅ **fileinfo** - File information
- ✅ **phar** - PHP Archive
- ✅ **posix** - POSIX functions
- ✅ **shmop** - Shared memory
- ✅ **sysvsem** - System V semaphores
- ✅ **sysvshm** - System V shared memory
- dba - Database abstraction
- ldap - LDAP
- mailparse - Email parsing
- mongodb - MongoDB driver
- oauth - OAuth
- pspell - Spell checking
- redis - Redis client
- tidy - HTML Tidy

#### Loaders/Optimizers (Production Only)
- ionCube Loader - Code protection
- SourceGuardian - Code protection
- ✅ **Zend OPcache** - Bytecode cache (in Docker)

## Docker Setup

### Currently Installed in Dockerfile
```dockerfile
RUN docker-php-ext-install -j"$(nproc)" \
    gd \
    mysqli \
    pdo_mysql \
    mbstring \
    exif \
    zip \
    bcmath \
    intl \
    opcache
```

### Comparison

| Extension | Production | Docker | Required | Status |
|-----------|-----------|--------|----------|--------|
| **mysqli** | ✅ | ✅ | Yes | ✅ Match |
| **pdo_mysql** | ✅ | ✅ | Yes | ✅ Match |
| **mbstring** | ✅ | ✅ | Yes | ✅ Match |
| **gd** | ✅ | ✅ | Yes | ✅ Match |
| **exif** | ✅ | ✅ | Yes | ✅ Match |
| **zip** | ✅ | ✅ | Yes | ✅ Match |
| **bcmath** | ✅ | ✅ | Yes | ✅ Match |
| **intl** | ✅ | ✅ | Yes | ✅ Match |
| **opcache** | ✅ | ✅ | Yes | ✅ Match |
| **curl** | ✅ | ⚠️ | Yes | ⚠️ **Missing in Docker** |
| **openssl** | ✅ | ⚠️ | Yes | ⚠️ **Built-in (verify)** |
| **json** | ✅ | ✅ | Yes | ✅ Built-in PHP 8.4 |
| **ctype** | ✅ | ✅ | Yes | ✅ Built-in PHP 8.4 |
| **fileinfo** | ✅ | ✅ | Yes | ✅ Built-in PHP 8.4 |
| **sodium** | ✅ | ✅ | Yes | ✅ Built-in PHP 8.4 |
| **xml** | ✅ | ⚠️ | Optional | ⚠️ **Should add** |
| **dom** | ✅ | ⚠️ | Optional | ⚠️ **Should add** |
| **SimpleXML** | ✅ | ⚠️ | Optional | ⚠️ **Should add** |
| **iconv** | ✅ | ⚠️ | Optional | ⚠️ **Should add** |

## Required for Your Application

Based on `composer.json` dependencies:

1. **Slim Framework** (slim/slim, slim/psr7)
   - Requires: mbstring, json, ctype
   - ✅ All present

2. **Google API Client** (google/apiclient)
   - Requires: curl, openssl, json, mbstring
   - ⚠️ curl needs verification

3. **Firebase JWT** (firebase/php-jwt)
   - Requires: openssl, json
   - ✅ Present

4. **Symfony YAML** (symfony/yaml)
   - Requires: ctype, mbstring
   - ✅ All present

5. **PHPDotenv** (vlucas/phpdotenv)
   - Requires: mbstring, pcre
   - ✅ All present

## Recommendations

### Critical (Must Add)
1. ✅ **curl** - Required for Google API Client
2. ✅ **openssl** - Required for JWT and HTTPS (verify it's enabled)

### Recommended (Should Add)
3. **xml/dom/SimpleXML** - Common dependencies, available on production
4. **iconv** - Character encoding, available on production

### Optional (Nice to Have)
5. **soap** - If you ever need SOAP APIs
6. **ftp** - Already available on production

## Action Items

1. ✅ Verify curl is available in Docker
2. ✅ Verify openssl is available in Docker
3. ✅ Add xml/dom/SimpleXML to Dockerfile
4. ✅ Add iconv to Dockerfile
5. ✅ Test deployment with updated Docker image
6. ✅ Document extension requirements

## Testing

After updating Dockerfile, verify all extensions:

```bash
# In Docker
docker compose exec backend php -m

# Compare with production
# Upload phpinfo.php to production and check
```

---

**Last Updated**: 2026-01-26
