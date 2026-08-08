# Changelog

## [1.2.0]

### Added

- LazyCollection and PHP Generator support
- Eloquent `cursor()`, `lazy()`, and `lazyById()` support
- Progress callbacks for downloads and stored exports
- Memory-friendly streamed processing for large datasets

### Changed

- CSV input is processed row-by-row without conversion to a normal Collection
- Stored exports spill temporary data larger than 5 MiB to disk
- The default CSV escape character is now empty for PHP 8.4 compatibility
- Removed the redundant `array` member from `iterable|Arrayable` documentation
