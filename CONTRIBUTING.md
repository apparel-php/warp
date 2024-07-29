# Contributing to Warp

Thank you for your interest in contributing to Warp (Web Application Routing Preprocessor)!

Warp is an extension library providing a robust, type-safe routing mechanism for the Woof framework. Before you suggest a feature, report a bug, or write any code, we strongly request that you understand and respect the core concepts and testing rules below.

## Our Core Philosophy: No Black Magic, Pure Object-Orientation

Warp is designed to resolve routes deterministically and safely. To maintain this philosophy, we enforce the following architectural rules:

1. **No Reflection or Magic Methods**: We explicitly avoid "black magic". Controllers must be resolved through explicit instantiation (`new`) rather than string-based dynamic class loading.
2. **Context Shifting and Delegation**: Complex routing should not be centralized. Instead, it must be delegated down a chain of `Resolver` classes, consuming the `RoutingContext` segment by segment.
3. **Separation of Concerns**: A `Resolver` should only determine the destination (`Target`). Any business logic or side effects (like database fetching) must be deferred to the `Controller`'s execution phase.

If a feature proposal or code change conflicts with this type-safe, pure object-oriented approach, it may be declined. We welcome ideas that further refine the elegance and strictness of Warp's routing ecosystem.

## How to Contribute

### 1. Discuss Major Changes via Issues First
If you plan to introduce new components or make significant architectural modifications, please open an issue before you start writing code. Let us discuss beforehand how your proposal aligns with Warp's philosophy.

### 2. Submitting Pull Requests
- Create a feature-specific topic branch branching off from the main branch.
- Keep your commit messages clear, concise, and descriptive.

## Coding Standards

To ensure code readability and consistency, we require all contributions to follow standard PHP conventions.

- **PSR-12 / PER Coding Style**: All PHP code must strictly adhere to the PSR-12 or PER Coding Style specification.

## Mandatory Testing & Mocking Rules

Warp inherits Woof's philosophy of exceptionally high testability. When fixing a bug or introducing a new feature, you must always accompany your changes with reproducible, pure unit tests using PHPUnit.

**Important Note on Mocking Woof Objects:**
When writing tests that require Woof-derived objects (such as `Request`, `Response`, or `WebEnvironment`), **do not use PHPUnit's built-in mocking framework** (e.g., `$this->createMock()`). 

Because Woof's architecture is completely decoupled from side effects, you can and should achieve everything using Woof's 100% pure API. Please instantiate real, deterministic Woof objects to write your tests. This ensures that the tests remain robust and true to the framework's behavior.

Before submitting your Pull Request, run the test suite locally and ensure that all tests pass:

```bash
vendor/bin/phpunit
```
