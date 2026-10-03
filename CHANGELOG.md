# Changelog

All notable changes to this extension are documented here. The format
is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.1.9] - 2026-10-03

### Fixed
- Custom contact form fields whose labels normalise to the same input name (for example "Phone #" and "Phone ?") now get unique names (a numeric suffix is added to the later ones), so each field keeps its own value. Fields with distinct names keep their existing names.
