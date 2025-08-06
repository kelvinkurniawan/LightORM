# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Priority 1 Implementation**:
  - Modular architecture with separated concerns
  - New `QueryBuilder` class with fluent interface
  - `DatabaseManager` with multi-database support (MySQL, PostgreSQL, SQLite)
  - Enhanced `Validator` class with custom rules support
  - `EventDispatcher` for model lifecycle events
  - Database-specific Grammar classes (MySQL, PostgreSQL, SQLite)
  - Connection classes for different database drivers
  - Helper functions for easier access to core components
  - Comprehensive interfaces/contracts for better extensibility
  - Environment-based configuration support (.env)
  - Enhanced transaction support with nested transactions

### Changed
- **Breaking Changes**:
  - Updated PHP requirement to ^8.0
  - Modularized Core components (split large Model.php)
  - Enhanced Database class now uses DatabaseManager internally
  - Improved namespacing and PSR-4 autoloading

### Enhanced
- Multi-database connection support
- Better error handling and validation
- Improved query building capabilities
- Enhanced testing infrastructure
- Better code organization and maintainability

### Added (Previous)
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
