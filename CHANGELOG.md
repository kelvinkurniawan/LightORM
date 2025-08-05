# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial release of LightORM
- Active Record pattern implementation
- Advanced Query Builder with fluent interface
- Soft deletes functionality
- Automatic timestamps (created_at, updated_at)
- Model validation with custom rules
- Event system with lifecycle hooks
- Attribute casting and mutators
- Query scopes support
- Mass assignment protection
- Model serialization capabilities
- Database transaction support
- Comprehensive documentation and examples

### Changed
- Refactored namespace from `App\Core` to `KelvinKurniawan\LightORM\Core`
- Made database configuration injectable instead of file-based
- Updated composer.json for library distribution

### Security
- Implemented mass assignment protection
- Added proper parameter binding for SQL injection prevention

## [1.0.0] - 2025-08-05

### Added
- Initial stable release ready for Composer distribution
