# API Pagination Documentation

This document describes the pagination implementation across all listing endpoints with mobile pull-to-refresh support.

## Overview

All listing endpoints now support pagination by default for better performance and mobile optimization:

- **Products**: `/api/products`
- **Categories**: `/api/categories` 
- **Customers**: `/api/customers`
- **Businesses**: `/api/businesses`
- **Sales**: `/api/sales`
- **Users**: `/api/users`

## Endpoint Behavior

### Default (Paginated Response)
All listing endpoints return paginated results by default.

**Query Parameters:**
- `page` (optional): Page number (default: 1, minimum: 1)
- `per_page` (optional): Items per page (default: 15, range: 1-100)
- `all` (optional): Set to "true" to get all items without pagination

**Examples:**
```
GET /api/products                    # Page 1, 15 items
GET /api/products?page=2             # Page 2, 15 items  
GET /api/products?page=1&per_page=20 # Page 1, 20 items
GET /api/products?all=true           # All products (no pagination)

GET /api/categories?page=3&per_page=10 # Page 3, 10 categories
GET /api/customers?all=true            # All customers (no pagination)
GET /api/sales?page=1&per_page=5       # Page 1, 5 sales
```

### Paginated Response Format
All paginated endpoints return the same consistent format:

```json
{
    "message": "Data retrieved successfully",
    "data": [
        {
            "id": 1,
            "name": "Item Name",
            // ... other item properties
        }
    ],
    "pagination": {
        "current_page": 1,
        "per_page": 15,
        "total": 150,
        "last_page": 10,
        "has_more_pages": true,
        "from": 1,
        "to": 15,
        "next_page_url": "http://your-app.com/api/endpoint?page=2",
        "prev_page_url": null
    }
}
```

### Non-Paginated Response Format
When using `?all=true`, endpoints return the original format:

```json
[
    {
        "id": 1,
        "name": "Item Name",
        // ... other item properties
    }
]
```

### Empty Response Format
When no data is found:

```json
{
    "message": "No data found",
    "data": [],
    "pagination": {
        "current_page": 1,
        "per_page": 15,
        "total": 0,
        "last_page": 1,
        "has_more_pages": false,
        "from": null,
        "to": null
    }
}
```

## Mobile Implementation Guide

### Pull-to-Refresh Implementation

#### Android (Kotlin/Java)
```kotlin
// SwipeRefreshLayout implementation
swipeRefreshLayout.setOnRefreshListener {
    currentPage = 1
    itemsList.clear()
    loadData(endpoint = "products", page = 1, isRefresh = true)
}

// Load more on scroll
recyclerView.addOnScrollListener(object : RecyclerView.OnScrollListener() {
    override fun onScrolled(recyclerView: RecyclerView, dx: Int, dy: Int) {
        super.onScrolled(recyclerView, dx, dy)
        
        val layoutManager = recyclerView.layoutManager as LinearLayoutManager
        val visibleItemCount = layoutManager.childCount
        val totalItemCount = layoutManager.itemCount
        val firstVisibleItemPosition = layoutManager.findFirstVisibleItemPosition()
        
        if (!isLoading && hasMorePages && 
            (visibleItemCount + firstVisibleItemPosition) >= totalItemCount - 3) {
            loadData(endpoint = currentEndpoint, page = currentPage + 1, isRefresh = false)
        }
    }
})

fun loadData(endpoint: String, page: Int, isRefresh: Boolean = false) {
    if (isRefresh) {
        swipeRefreshLayout.isRefreshing = true
    } else {
        isLoading = true
    }
    
    apiService.getData(endpoint = endpoint, page = page, perPage = 15)
        .enqueue(object : Callback<ApiResponse> {
            override fun onResponse(call: Call<ApiResponse>, response: Response<ApiResponse>) {
                if (response.isSuccessful) {
                    val apiResponse = response.body()
                    
                    if (isRefresh) {
                        itemsList.clear()
                        currentPage = 1
                    }
                    
                    apiResponse?.data?.let { items ->
                        itemsList.addAll(items)
                        adapter.notifyDataSetChanged()
                    }
                    
                    hasMorePages = apiResponse?.pagination?.has_more_pages ?: false
                    if (!isRefresh) currentPage++
                }
                
                swipeRefreshLayout.isRefreshing = false
                isLoading = false
            }
            
            override fun onFailure(call: Call<ApiResponse>, t: Throwable) {
                swipeRefreshLayout.isRefreshing = false
                isLoading = false
            }
        })
}
```

#### iOS (Swift)
```swift
// UIRefreshControl implementation
let refreshControl = UIRefreshControl()
refreshControl.addTarget(self, action: #selector(refreshData), for: .valueChanged)
tableView.refreshControl = refreshControl

@objc func refreshData() {
    currentPage = 1
    items.removeAll()
    loadData(endpoint: currentEndpoint, page: 1, isRefresh: true)
}

// Load more on scroll
func scrollViewDidScroll(_ scrollView: UIScrollView) {
    let offsetY = scrollView.contentOffset.y
    let contentHeight = scrollView.contentSize.height
    let height = scrollView.frame.size.height
    
    if offsetY > contentHeight - height * 1.5 && !isLoading && hasMorePages {
        loadData(endpoint: currentEndpoint, page: currentPage + 1)
    }
}

func loadData(endpoint: String, page: Int, isRefresh: Bool = false) {
    if !isRefresh {
        isLoading = true
    }
    
    APIService.shared.getData(endpoint: endpoint, page: page, perPage: 15) { [weak self] result in
        DispatchQueue.main.async {
            switch result {
            case .success(let response):
                if isRefresh {
                    self?.items.removeAll()
                    self?.currentPage = 1
                }
                
                self?.items.append(contentsOf: response.data)
                self?.hasMorePages = response.pagination.hasMorePages
                
                if !isRefresh {
                    self?.currentPage += 1
                }
                
                self?.tableView.reloadData()
                
            case .failure(let error):
                print("Error loading data: \(error)")
            }
            
            self?.refreshControl.endRefreshing()
            self?.isLoading = false
        }
    }
}
```

#### React Native
```javascript
import React, { useState, useEffect } from 'react';
import { FlatList, RefreshControl } from 'react-native';

const DataList = ({ endpoint }) => {
    const [items, setItems] = useState([]);
    const [currentPage, setCurrentPage] = useState(1);
    const [isLoading, setIsLoading] = useState(false);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [hasMorePages, setHasMorePages] = useState(true);

    const loadData = async (page = 1, isRefresh = false) => {
        if (isRefresh) {
            setIsRefreshing(true);
        } else {
            setIsLoading(true);
        }

        try {
            const response = await fetch(
                `${API_BASE_URL}/${endpoint}?page=${page}&per_page=15`,
                {
                    headers: {
                        'Authorization': `Bearer ${authToken}`,
                        'Content-Type': 'application/json',
                    },
                }
            );

            const data = await response.json();

            if (isRefresh) {
                setItems(data.data);
                setCurrentPage(1);
            } else {
                setItems(prev => [...prev, ...data.data]);
            }

            setHasMorePages(data.pagination.has_more_pages);
            if (!isRefresh) setCurrentPage(page);

        } catch (error) {
            console.error('Error loading data:', error);
        } finally {
            setIsLoading(false);
            setIsRefreshing(false);
        }
    };

    const handleRefresh = () => {
        loadData(1, true);
    };

    const handleLoadMore = () => {
        if (!isLoading && hasMorePages) {
            loadData(currentPage + 1);
        }
    };

    useEffect(() => {
        loadData(1);
    }, [endpoint]);

    return (
        <FlatList
            data={items}
            keyExtractor={(item) => item.id.toString()}
            renderItem={({ item }) => <ItemComponent item={item} />}
            refreshControl={
                <RefreshControl
                    refreshing={isRefreshing}
                    onRefresh={handleRefresh}
                />
            }
            onEndReached={handleLoadMore}
            onEndReachedThreshold={0.1}
            ListFooterComponent={isLoading && !isRefreshing ? <LoadingSpinner /> : null}
        />
    );
};
```

## Features

### 1. **Consistent API Design**
- All listing endpoints follow the same pagination pattern
- Uniform response format across all endpoints
- Same query parameters for all endpoints

### 2. **Performance Optimized**
- Default 15 items per page for mobile optimization
- Maximum 100 items per page to prevent memory issues
- Ordered by creation date (newest first) for all endpoints

### 3. **Mobile-Friendly Response**
- `has_more_pages`: Boolean to easily check if more data exists
- `current_page` and `last_page` for progress indicators
- `total` count for showing "X of Y" indicators
- `next_page_url` and `prev_page_url` for easy navigation

### 4. **Backward Compatibility**
- Pagination is now the default behavior for better performance
- Use `?all=true` parameter to get all items without pagination if needed
- Consistent endpoint URLs with smart behavior based on parameters

### 5. **Developer Experience**
- Centralized pagination logic in base Controller class
- Consistent validation and error handling
- Easy to extend to new endpoints

## Authentication

All endpoints require authentication via Sanctum token:

```
Authorization: Bearer {your-token}
```

## API Endpoints Reference

| Endpoint | Resource | Description |
|----------|----------|-------------|
| `GET /api/products` | Products | List all products (paginated) |
| `GET /api/categories` | Categories | List all categories (paginated) |
| `GET /api/customers` | Customers | List all customers (paginated) |
| `GET /api/businesses` | Businesses | List all businesses (paginated) |
| `GET /api/sales` | Sales | List all sales (paginated) |
| `GET /api/users` | Users | List all users (paginated) |

## Error Handling

All endpoints return consistent error responses:

```json
{
    "message": "An error occurred while retrieving the data",
    "error": "Detailed error message"
}
```

## Rate Limiting

Consider implementing rate limiting on pagination endpoints:

```php
// In routes/api.php
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::apiResource('products', ProductController::class);
    Route::apiResource('categories', CategoryController::class);
    // ... other resources
});
```

## Caching Recommendations

For better performance, consider implementing Redis caching:

```php
// In Repository classes
public function getPaginatedItems($perPage = 15, $page = 1)
{
    $cacheKey = "items_page_{$page}_per_{$perPage}";
    
    return Cache::remember($cacheKey, 300, function () use ($perPage, $page) {
        return $this->model->orderBy('created_at', 'desc')
                          ->paginate($perPage, ['*'], 'page', $page);
    });
}
```
