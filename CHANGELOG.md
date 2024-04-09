# Changelog

All notable changes to this project will be documented in this file, in reverse chronological order by release.

## [Unreleased]

## 1.8.3 - 2024-04-09

### Changed

- Updated dependencies. #70336

## 1.8.2 - 2024-03-05

### Changed

- Updated dependencies. #69762

## 1.8.1 - 2024-02-06

### Changed

- Updated dependencies. #69054

## 1.8 - 2023-10-23

### Changed

- Cotor now adds the `composer.lock` to the git repo. Use `cotor.phar install --no-lock` for the old behaviour. #65950
- Increased minimum version of symfony/console to get rid of deprecation warnings. #65695

### Removed

- Removed legacy self cleanup.

## 1.7.9 - 2023-08-24

### Fixed

- Fixed quoting in release job again.

## 1.7.8 - 2023-08-24

### Fixed

- Fixed quoting in release job.

## 1.7.7 - 2023-08-24

### Fixed

- Fixed dotenv format again again.

## 1.7.6 - 2023-08-24

### Fixed

- Fixed dotenv format again.

## 1.7.5 - 2023-08-24

### Fixed

- Fixed dotenv format.

## 1.7.4 - 2023-08-24

### Fixed

- Fixed newlines in release description.

## 1.7.3 - 2023-08-24

### Fixed

- Fixed uploading PHAR and release description.

## 1.7.2 - 2023-08-24

### Fixed

- Fixed bad commands in upload job.

## 1.7.1 - 2023-08-24

### Fixed

- Fixed CI configuration.

## 1.7 - 2023-08-24

### Added

- Added Debian package. #65597

## 1.6 - 2023-08-04

### Added

- Added `version` argument to `extend` command. #63975

## 1.5 - 2022-05-13

### Added

- Added `outdated` command. #47341

### Fixed

- Fixed `update-all` command not finding any tools.

## 1.4 - 2022-04-12

### Changed

- Upgraded box build und tools. #48850

## 1.3 - 2021-11-17

### Changed

- [BC BREAK] Removed phive replacement support. #44254 

### Fixed

- Use proper path on reading package version for new tool installations. #44253
 
## 1.2.1 - 2021-10-29

### Fixed

- Use relative paths for phar symlinks.

## 1.2 - 2021-10-29

### Changed

- Updated tool integration with new extensionless executable and hidden tool directory. #43650

## 1.1 - 2021-10-12

### Added

- Allow installing extensions for tools. #42932

## 1.0.2 - 2021-10-04

### Fixed

- Fixed using Composer "extras" instead of "extra".

## 1.0.1 - 2021-09-29

### Fixed

- Fixed making phars executable.

## 1.0 - 2021-09-28

### Added

- Initial release of cotor.

