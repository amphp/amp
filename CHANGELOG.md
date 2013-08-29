v0.2.0
------

- Added `Alert\Forkable` interface for safely forking event reactors without resource corruption
- `Alert\LibeventReactor` now implements `Alert\Forkable`

> **BC BREAKS**: *none*

#### v0.1.2

- Addressed execution time drift in repeating native reactor alarms

#### v0.1.1

- Addressed infinite recursion in repeating callbacks

v0.1.0
------

- Initial tagged release
