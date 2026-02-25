# API Endpoint Optimization Analysis

**Document Created**: 2026-01-13
**Last Updated**: 2026-01-13 (Revised to respect API type boundaries)
**Total Current Endpoints**: 408
**Non-DynamicEntity Endpoints**: 104

**Target Reduction**: ~12-13 endpoints (~12% reduction of non-dynamic endpoints, ~3% reduction overall)

**Important**: This analysis respects API type boundaries. BackendApi (admin/merchant) endpoints cannot be consolidated with customer-facing APIs (RestApi/StorefrontApi).

**Note**: DynamicEntity endpoints (304 total, ~74% of all endpoints) are analyzed separately in [dynamic-api.md](./dynamic-api.md).

---

## Executive Summary

This document identifies opportunities to consolidate and refactor existing API endpoints (excluding DynamicEntity) to reduce complexity, improve maintainability, and provide a more consistent API experience. The analysis focuses on patterns of duplication and opportunities for consolidation without losing functionality.

**Key Findings:**
1. **Authentication & MFA**: ~8 endpoint reduction through OAuth 2.0 patterns (respecting API type boundaries)
2. **Role-Based Duplication**: ~4 endpoint reduction by using authentication context instead of separate endpoints
3. **DynamicEntity Endpoints**: See [dynamic-api.md](./dynamic-api.md) for detailed analysis of the 304 DynamicEntity endpoints

**Important Note**: This analysis respects API type boundaries. BackendApi (admin/merchant operations) cannot be merged with customer-facing APIs (StorefrontApi/RestApi) due to different authentication, authorization, and functional requirements.

---

## API Type Architecture

### Overview

Spryker uses three distinct API types that serve different purposes and cannot be consolidated:

| API Type | Purpose | Authentication | Audience |
|----------|---------|---------------|----------|
| **BackendApi** | Administrative and merchant operations | Admin/merchant credentials | Internal staff, merchants |
| **StorefrontApi** | Customer-facing storefront operations | Customer auth, guest sessions | End customers |
| **RestApi** | Legacy customer-facing API | Customer auth, guest sessions | End customers, third-party integrations |

### Why API Types Cannot Be Merged

1. **Different Authentication Models**
   - BackendApi: Admin/merchant authentication with elevated privileges
   - StorefrontApi/RestApi: Customer authentication with restricted access

2. **Different Authorization Requirements**
   - BackendApi: Role-based access control for admin operations
   - Customer APIs: Customer-scoped data access only

3. **Different Functional Requirements**
   - BackendApi: Management operations (CRUD for configuration)
   - Customer APIs: Read-heavy operations, transactional flows

4. **Security Boundaries**
   - Merging would expose admin operations to customer API surface
   - Increases attack surface and security risks

### Valid Consolidation Opportunities

**Within Same API Type:**
- Multiple endpoints serving same purpose in BackendApi can be consolidated
- StorefrontApi and RestApi endpoints can potentially be consolidated (both customer-facing)

**Across Different Roles (Same API Type):**
- Agent vs Customer endpoints can use authentication context
- Company User vs Regular User can use role-based filtering

**Invalid Consolidation:**
- ❌ BackendApi + StorefrontApi/RestApi
- ❌ Admin operations + Customer operations
- ❌ Management APIs + Transactional APIs

---

## 1. Authentication/Token Endpoints

### Current State
**Total Endpoints**: 7 separate token endpoints

**Customer-Facing APIs (RestApi):**
| Endpoint | Method | API Type | Summary |
|----------|--------|----------|---------|
| `/access-tokens` | POST | RestApi | Creates access token for user |
| `/agent-access-tokens` | POST | RestApi | Creates agent's access token |
| `/agent-customer-impersonation-access-tokens` | POST | RestApi | Creates customer impersonation access token |
| `/company-user-access-tokens` | POST | RestApi | Creates access token for company user |
| `/refresh-tokens` | POST | RestApi | Refreshes customer's auth token |
| `/token` | POST | RestApi | Creates access token for user |

**Backend APIs (BackendApi):**
| Endpoint | Method | API Type | Summary |
|----------|--------|----------|---------|
| `/warehouse-tokens` | POST | BackendApi | Creates warehouse access tokens |
| `/token` | POST | BackendApi | Creates access token for user |

### Issues
- Multiple endpoints serving similar purposes with slight variations **within same API type**
- Inconsistent naming patterns (`*-tokens` vs `*-access-tokens`)
- Difficult to maintain and extend for new user types
- Increases API surface area unnecessarily

### Refactoring Plan

**Customer-Facing APIs (RestApi) - Consolidate to 2 endpoints:**

1. **`POST /tokens`** - Token creation
   - Request body parameter `grant_type` to differentiate:
     - `password` (regular customer)
     - `agent_credentials`
     - `agent_impersonation`
     - `company_user`
   - Request body parameter `user_type` (optional, for clarity)

2. **`POST /tokens/refresh`** - Token refresh
   - Accepts refresh token
   - Returns new access token

**Backend APIs (BackendApi) - Keep separate:**

1. **`POST /token`** - Admin/merchant token creation
2. **`POST /warehouse-tokens`** - Warehouse user tokens (if functionally different)
   - Or consolidate into `/token` with grant type if similar

**Benefits:**
- Follows OAuth 2.0 patterns
- Single endpoint per API type to maintain for token generation
- Easy to extend for new user types within each API type
- Clear separation between token creation and refresh
- **Respects API type boundaries for security**

**Estimated Savings**: ~4 endpoints (RestApi consolidation only)

**Note**: BackendApi endpoints remain separate from customer-facing APIs due to different authentication models and security requirements.

---

## 2. Multi-Factor Authentication Endpoints

### Current State
**Total Endpoints**: 15 endpoints (5 resources × 3 API types)

Each MFA resource is duplicated across three API types:

| Endpoint | Method | APIs | Summary |
|----------|--------|------|---------|
| `/multi-factor-auth-trigger` | POST | BackendApi + RestApi + StorefrontApi | Triggers sending of MFA code |
| `/multi-factor-auth-type-activate` | POST | BackendApi + RestApi + StorefrontApi | Activates MFA type |
| `/multi-factor-auth-type-deactivate` | POST | BackendApi + RestApi + StorefrontApi | Deactivates MFA type |
| `/multi-factor-auth-type-verify` | POST | BackendApi + RestApi + StorefrontApi | Verifies MFA code |
| `/multi-factor-auth-types` | GET | BackendApi + RestApi + StorefrontApi | Retrieves MFA types |

### Issues
- **Cannot merge BackendApi with customer-facing APIs** due to different authentication contexts
- RestApi and StorefrontApi duplication may be valid for different customer authentication flows
- Increases maintenance burden for duplicate implementations

### Refactoring Plan

**IMPORTANT**: BackendApi MFA endpoints **must remain separate** from customer-facing APIs.

**Option 1: Keep API Type Separation (Recommended)**

**BackendApi (5 endpoints):**
- Serve admin/merchant MFA needs
- Keep current endpoints for backend authentication

**Customer-Facing APIs - Consolidate RestApi + StorefrontApi (5 endpoints):**

1. **`POST /auth/mfa/trigger`** - Trigger MFA code
2. **`POST /auth/mfa/types/{type}/activate`** - Activate MFA type
3. **`POST /auth/mfa/types/{type}/deactivate`** - Deactivate MFA type
4. **`POST /auth/mfa/verify`** - Verify MFA code
5. **`GET /auth/mfa/types`** - List available MFA types

Use authentication context to determine RestApi vs StorefrontApi behavior if needed.

**Option 2: Keep Current Structure**
- Maintain all 15 endpoints if RestApi and StorefrontApi serve different purposes
- Document clear use case distinction

**Benefits (Option 1):**
- Consolidates duplicate customer-facing MFA logic
- **Maintains security boundary between backend and customer APIs**
- Reduces maintenance burden for customer MFA
- Simplified API structure for customers

**Estimated Savings**: ~5 endpoints (consolidating RestApi + StorefrontApi only)

**Note**: Original estimate of ~10 endpoints was incorrect as it assumed BackendApi could be merged with customer APIs, which violates security boundaries.

---

## 3. Quote Requests (Role-Based Duplication)

### Current State
**Total Endpoints**: 4 endpoints (2 resources × 2 operations each)

| Endpoint | Method | Summary | User Type |
|----------|--------|---------|-----------|
| `/quote-requests` | GET | Retrieves quote request list | Company User |
| `/quote-requests` | POST | Creates quote request | Company User |
| `/agent-quote-requests` | GET | Retrieves quote request list | Agent |
| `/agent-quote-requests` | POST | Creates quote request as agent | Agent |

### Issues
- Same resource split by user role
- Authentication already identifies user type
- Unnecessary API duplication

### Refactoring Plan

**Consolidate to 1 resource:**

1. **`GET /quote-requests`** - List quote requests
   - Automatically filtered by user role (from token)
   - Agents see all requests
   - Company users see their own requests

2. **`POST /quote-requests`** - Create quote request
   - Behavior determined by authenticated user type
   - Validation rules applied based on role

**Benefits:**
- Single resource to maintain
- Role-based filtering automatic
- Consistent with REST principles

**Estimated Savings**: ~2 endpoints

---

## 4. Service Points (API Type Duplication)

### Current State
**Total Endpoints**: 3 endpoints

| Endpoint | Method | API Type | Summary |
|----------|--------|----------|---------|
| `/service-points` | GET | BackendApi | Retrieves service points collection (admin view) |
| `/service-points` | POST | BackendApi | Creates service point (admin operation) |
| `/service-points` | GET | RestApi | Retrieves service points collection (customer view) |

### Issues
- GET operation appears duplicated, but serves different purposes
- BackendApi: Admin management and configuration
- RestApi: Customer discovery of available service points

### Refactoring Plan

**Analysis**: These endpoints serve **different purposes** and **must remain separate**:

**BackendApi (2 endpoints):**
- **Admin/merchant functionality**: Create and manage service points
- **Authentication**: Requires admin/merchant credentials
- **Operations**: Full CRUD operations
- **Data**: May include admin-only fields (internal IDs, configuration)

**RestApi (1 endpoint):**
- **Customer functionality**: Discover available service points for orders
- **Authentication**: Customer credentials or public access
- **Operations**: Read-only
- **Data**: Customer-facing fields only (name, address, hours)

**Recommended**: **Keep separation** (Option 2)
- Document clear use case distinction
- Different data models and permissions justify separate endpoints

**Estimated Savings**: 0 endpoints (separation required for different audiences)

---

## 5. Shipment Types (API Type Duplication)

### Current State
**Total Endpoints**: 3 endpoints

| Endpoint | Method | API Type | Summary |
|----------|--------|----------|---------|
| `/shipment-types` | GET | BackendApi | Retrieves shipment types collection (admin view) |
| `/shipment-types` | POST | BackendApi | Creates shipment type (admin operation) |
| `/shipment-types` | GET | RestApi | Retrieves shipment types collection (customer view) |

### Issues
- GET operation appears duplicated, but serves different purposes
- Same pattern as Service Points

### Refactoring Plan

**Analysis**: Same as Service Points - these endpoints serve **different purposes** and **must remain separate**:

**BackendApi (2 endpoints):**
- **Admin/merchant functionality**: Create and manage shipment types
- **Authentication**: Requires admin/merchant credentials
- **Operations**: Full CRUD operations
- **Data**: May include pricing rules, carrier configuration, internal settings

**RestApi (1 endpoint):**
- **Customer functionality**: View available shipment options during checkout
- **Authentication**: Customer credentials or public access
- **Operations**: Read-only
- **Data**: Customer-facing fields only (name, description, estimated delivery time, price)

**Recommended**: **Keep separation**
- Different audiences require different data and permissions
- Admin configuration vs customer selection are distinct use cases

**Estimated Savings**: 0 endpoints (separation required for different audiences)

---

## 6. Stores (Customer API Duplication)

### Current State
**Total Endpoints**: 2 endpoints

| Endpoint | Method | API Type | Summary |
|----------|--------|----------|---------|
| `/stores` | GET | RestApi | Retrieves store data (dynamic store aware) |
| `/stores` | GET | StorefrontApi | Retrieves store collection |

### Issues
- Same endpoint duplicated across customer-facing APIs
- Both serve customer needs (store discovery/selection)
- Behavior difference could be handled with query parameters

### Refactoring Plan

**Analysis**: Both RestApi and StorefrontApi are customer-facing and serve similar purposes. **Consolidation is valid**.

**Consolidate to 1 endpoint with query parameters:**

**`GET /stores`** (in unified customer API)
- Query parameter `?scope=current` - Returns current store (default)
- Query parameter `?scope=all` - Returns all stores (if dynamic store enabled)
- Or use authentication context to determine behavior

**Benefits:**
- Single endpoint for customer store discovery
- Reduces duplication between legacy RestApi and StorefrontApi
- Easier to maintain and test

**Estimated Savings**: ~1 endpoint

**Note**: This is a valid consolidation because both APIs serve the same audience (customers) with the same authentication model.

---

## 7. Availability Notifications (Context Prefix Pattern)

### Current State
**Total Endpoints**: 2 endpoints

| Endpoint | Method | Summary |
|----------|--------|---------|
| `/availability-notifications` | POST | Subscribe to product back-in-stock notifications |
| `/my-availability-notifications` | GET | Retrieve current user's notification subscriptions |

### Issues
- `/my-*` prefix pattern is non-standard REST
- Should use authentication context instead of URL prefix
- Inconsistent with REST principles

### Refactoring Plan

**Consolidate to 1 resource:**

1. **`POST /availability-notifications`** - Subscribe to notifications
2. **`GET /availability-notifications`** - Get subscriptions
   - Automatically filtered by authenticated user
   - No need for `/my-` prefix

**Benefits:**
- Standard REST resource naming
- Authentication context provides filtering
- More intuitive API design

**Estimated Savings**: Organizational improvement (cleaner design, same endpoint count)

---

## 8. Carts (Guest vs Authenticated Duplication)

### Current State
**Total Endpoints**: 3 endpoints

| Endpoint | Method | Summary |
|----------|--------|---------|
| `/carts` | GET | Retrieves list of customer's carts |
| `/carts` | POST | Creates a cart |
| `/guest-carts` | GET | Retrieves list of guest carts |

### Issues
- Separate endpoint for guest users
- Authentication state should determine cart type
- Session/token already identifies guest vs authenticated

### Refactoring Plan

**Consolidate to 1 resource:**

**`/carts`** (GET, POST)
- Use authentication state (token presence/absence) to determine behavior
- Authenticated: Returns customer carts
- Guest: Returns guest carts (session-based)
- No separate endpoint needed

**Benefits:**
- Single cart API for all users
- Simplified client code
- Consistent behavior

**Estimated Savings**: ~1 endpoint

---

## 9. Product Attributes (Potential Overlap)

### Current State
**Total Endpoints**: 3 endpoints

| Endpoint | Method | API Type | Summary |
|----------|--------|----------|---------|
| `/product-attributes` | GET | BackendApi | Get Product Attribute collection |
| `/product-attributes` | POST | BackendApi | Creates Product Attribute |
| `/product-management-attributes` | GET | RestApi | (Details unclear from spec) |

### Issues
- Potential overlap between endpoints
- Naming suggests similar functionality
- Need clarification on use case differences

### Refactoring Plan

**Investigation Required:**
1. Determine if `product-management-attributes` serves different purpose
2. If similar, consolidate to `/product-attributes` in BackendApi
3. If different, document clear distinction

**Potential Savings**: ~1 endpoint (if overlap confirmed)

---

## Consolidation Summary

**Note**: This summary excludes DynamicEntity endpoints. See [dynamic-api.md](./dynamic-api.md) for DynamicEntity analysis.

**IMPORTANT**: This revised analysis respects API type boundaries. BackendApi endpoints cannot be merged with customer-facing APIs (RestApi/StorefrontApi).

| Category | Current Endpoints | Proposed Endpoints | Savings | Priority | Notes |
|----------|-------------------|-------------------|---------|----------|-------|
| **Token/Authentication** | 7 | 4 | ~4* | High | RestApi consolidation only |
| **Multi-Factor Auth** | 15 | 10 | ~5 | High | RestApi + StorefrontApi consolidation only |
| **Quote Requests** | 4 | 2 | ~2 | Medium | Role-based consolidation (valid) |
| **Service Points** | 3 | 3 | 0 | N/A | **Must remain separate** (different audiences) |
| **Shipment Types** | 3 | 3 | 0 | N/A | **Must remain separate** (different audiences) |
| **Stores** | 2 | 1 | ~1 | Low | Customer API consolidation (valid) |
| **Carts** | 3 | 2 | ~1 | Medium | Guest/auth consolidation (valid) |
| **Product Attributes** | 3 | 2-3 | 0-1 | Low | Investigation required |
| **TOTAL** | **~40** | **~27-28** | **~12-13** | - | |

**Overall Reduction**: ~12-13 endpoints (~12% reduction of non-DynamicEntity endpoints, ~3% overall)

**Key Changes from Original Analysis:**
- **Original claim**: ~22 endpoints reduction (incorrect)
- **Revised estimate**: ~12-13 endpoints reduction (respects API type boundaries)
- **Service Points & Shipment Types**: No consolidation (0 instead of 2 endpoints saved)
- **Multi-Factor Auth**: Half the original savings (5 instead of 10 endpoints saved)
- **Authentication/Token**: Slightly reduced savings (4 instead of 5 endpoints saved)

---

## Implementation Roadmap

**Note**: DynamicEntity implementation roadmap is documented separately in [dynamic-api.md](./dynamic-api.md).

### Phase 1: Authentication/Token Unification (Customer APIs)
**Estimated Savings**: ~4 endpoints
**Priority**: High
**Estimated Effort**: Medium

**Scope**: RestApi token endpoints consolidation only

**Steps:**
1. Implement OAuth 2.0 compliant token endpoint for customer authentication
2. Add grant type parameter support (password, agent_credentials, agent_impersonation, company_user)
3. Implement user type differentiation logic
4. Create token refresh endpoint
5. Update authentication middleware for customer APIs
6. Migrate existing RestApi token endpoints
7. Update client libraries
8. Deprecate old customer token endpoints

**Important**: **BackendApi token endpoints remain separate** and are not affected by this phase.

**Risks:**
- Breaking change for customer authentication flow
- Client migration required
- Security testing critical

---

### Phase 2: Multi-Factor Auth Consolidation (Customer APIs)
**Estimated Savings**: ~5 endpoints
**Priority**: High
**Estimated Effort**: Medium

**Scope**: RestApi + StorefrontApi MFA consolidation only

**Steps:**
1. Create unified customer MFA API namespace
2. Implement context-aware routing for RestApi/StorefrontApi
3. Consolidate duplicate customer MFA logic
4. Update customer authentication handling
5. Migrate customer MFA endpoints
6. Deprecate old customer MFA endpoints

**Important**: **BackendApi MFA endpoints (5 endpoints) remain completely separate** due to different authentication contexts and security requirements.

**Risks:**
- Security-sensitive feature
- Must maintain backward compatibility during migration
- Thorough testing required

---

### Phase 3: Customer API Consolidation
**Estimated Savings**: ~1 endpoint
**Priority**: Low
**Estimated Effort**: Small

**Scope**: Consolidate duplicate customer-facing endpoints only

**Steps:**
1. Consolidate Stores endpoint (RestApi + StorefrontApi)
2. Use authentication context for behavior differentiation
3. Update routing configuration
4. Deprecate duplicate customer endpoints

**Affected Resources:**
- Stores (RestApi + StorefrontApi consolidation)

**Note**: Service Points and Shipment Types **are NOT part of this phase** as they serve different audiences and must remain separate.

---

### Phase 4: Role-Based Consolidation
**Estimated Savings**: ~3 endpoints
**Priority**: Medium
**Estimated Effort**: Small-Medium

**Scope**: Role-based endpoint consolidation within same API type

**Steps:**
1. Implement role-based filtering in controllers
2. Use token claims for user type determination
3. Consolidate agent/customer/company-user endpoints
4. Update authorization logic
5. Deprecate role-specific endpoints

**Affected Resources:**
- Quote Requests (~2 endpoints)
- Carts (~1 endpoint)
- Availability Notifications (organizational improvement)

---

## Benefits of Consolidation

### For Developers
- **Reduced Complexity**: Fewer endpoints to understand and maintain
- **Consistent Patterns**: Predictable API design across resources
- **Easier Testing**: Less code paths to test
- **Better Maintainability**: Changes in one place affect all resources

### For API Consumers
- **Simpler Integration**: Fewer endpoints to learn
- **Consistent Experience**: Same patterns across all resources
- **Better Documentation**: Less overwhelming, more focused
- **Easier Discovery**: Clear API structure

### For Operations
- **Reduced Attack Surface**: Fewer endpoints to secure
- **Easier Monitoring**: Less endpoints to track
- **Simplified Deployment**: Fewer moving parts
- **Better Performance**: Optimized generic handlers

---

## Migration Strategy

### Versioning Approach
1. **Introduce new endpoints** in API v2
2. **Keep old endpoints** in API v1 (deprecated)
3. **Provide migration guide** for clients
4. **Set deprecation timeline** (e.g., 12 months)
5. **Monitor usage** of old endpoints
6. **Remove old endpoints** after timeline

### Client Communication
1. **Announce changes** early and clearly
2. **Provide migration examples** for each endpoint
3. **Offer migration tools** where possible
4. **Set up monitoring** for old endpoint usage
5. **Provide support** during migration period

### Backward Compatibility
- Maintain old endpoints during deprecation period
- Proxy old endpoints to new implementation
- Log usage of deprecated endpoints
- Send deprecation headers in responses

---

## Risks and Mitigation

| Risk | Impact | Mitigation |
|------|--------|------------|
| Breaking changes for clients | High | Versioning strategy, long deprecation period |
| Performance regression | Medium | Thorough performance testing, optimization |
| Security vulnerabilities | High | Security audit, penetration testing |
| Data loss during migration | Critical | Comprehensive backup, rollback plan |
| Increased complexity in generic handlers | Medium | Clear architecture, robust testing |
| Client adoption resistance | Medium | Clear communication, migration support |

---

## Success Metrics

### Technical Metrics
- **Endpoint Count (Non-DynamicEntity)**: Target reduction from ~104 to ~91-92 (~12-13 endpoints)
- **Overall Endpoint Count**: Target reduction from 408 to ~395-396 (~3% reduction)
- **Code Complexity**: Reduced cyclomatic complexity in customer authentication
- **Test Coverage**: Maintain >80% coverage
- **Performance**: No regression in response times
- **Error Rates**: Maintain or improve current rates

### Business Metrics
- **API Documentation Size**: Reduced by ~12% (for non-DynamicEntity endpoints)
- **Developer Onboarding Time**: Improved through clearer API type boundaries
- **Integration Time**: Reduced for customer-facing authentication flows
- **Support Tickets**: Reduced authentication-related issues
- **API Usage**: Increased adoption of consolidated customer endpoints

**Note**: Original metrics projecting 70% documentation reduction were based on incorrect assumption that BackendApi and customer APIs could be merged.

---

### Confirmed Optimizations

**REVISED ANALYSIS**: This analysis identified **~12-13 endpoint reduction** opportunities, respecting API type boundaries:

- **Authentication/Token endpoints**: ~4 endpoint reduction through OAuth 2.0 patterns (RestApi only)
- **Multi-Factor Auth**: ~5 endpoint reduction through context-aware routing (RestApi + StorefrontApi only)
- **Role-Based & Context-Based**: ~3-4 endpoint reduction using authentication context (Carts, Quote Requests, Stores)

**Key Constraint**: BackendApi endpoints **cannot be merged** with customer-facing APIs (RestApi/StorefrontApi) due to:
- Different authentication models
- Different authorization requirements
- Different security boundaries
- Different data models and business logic

### DynamicEntity Endpoints

The **DynamicEntity endpoints** (304 endpoints, ~74% of total API) require separate detailed analysis. See [dynamic-api.md](./dynamic-api.md) for:
- Complete resource listing and analysis
- Three consolidation options (Full, Partial, None)
- Investigation requirements and decision criteria
- Migration strategies and considerations

### Key Benefits

- **Clearer API architecture** with explicit separation between BackendApi and customer APIs
- **Improved security** by maintaining distinct API type boundaries
- **Better maintainability** with consolidated customer authentication and MFA patterns
- **Reduced duplication** in customer-facing authentication flows
- **Easier to extend** for new customer user types and authentication methods
- **Cleaner codebase** for customer API implementations

### Critical Next Steps

1. **Customer Authentication & MFA Consolidation** (High Priority)
   - Implement OAuth 2.0 compliant token endpoint for customer APIs (RestApi)
   - Consolidate customer MFA endpoints (RestApi + StorefrontApi) with context-aware routing
   - **Keep BackendApi authentication completely separate**
   - Update client libraries and documentation

2. **Role-Based Consolidation** (Medium Priority)
   - Use authentication context instead of separate endpoints
   - Consolidate role-based endpoints within same API type:
     - Quote Requests (Agent vs Customer)
     - Carts (Guest vs Authenticated)
     - Stores (RestApi + StorefrontApi only)

3. **API Type Documentation** (High Priority)
   - Document clear boundaries between BackendApi and customer APIs
   - Explain why Service Points, Shipment Types, and similar resources have separate endpoints
   - Provide guidelines for when consolidation is appropriate vs when separation is required

4. **DynamicEntity Strategy** (Requires Investigation)
   - See [dynamic-api.md](./dynamic-api.md) for complete analysis
   - Decision on consolidation approach needed
   - Potential for 0-300 endpoint reduction
