# Changelog

All notable changes to this extension are documented here. The format
is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.1.12] - 2026-10-06

### Changed
- The contact form uses the shared form style on both stores. Inputs, the select and the message box are white with a 1px #D4D4D4 border and 16px text at every width. Before, they had a grey #F9FAFB fill, a 1.5px border and 14px text on desktop. Focus shows a 2px teal ring, labels are 14px semibold (13px before), error text is 14px in #B91C1C (12px before), and rows are 16px apart (20px before).
- Send Message is 44px tall (48px on phones) with 15px semibold text. Before, it was 50px with bold text.
- The page title is 36px (28px on phones). The intro line is 16px and at most 760px wide; before, it ran 1232px wide at 1440px.

### Added
- Unit tests for the contact form styles.
