# Product Pagination API

This document describes the pagination implementation for products with mobile pull-to-refresh support.

## Endpoints

### 1. Get Products (Paginated by Default)
**GET** `/api/products`

Returns paginated products by default. This is optimized for mobile applications with pull-to-refresh functionality.

**Query Parameters:**
- `page` (optional): Page number (default: 1, minimum: 1)
- `per_page` (optional): Items per page (default: 15, range: 1-100)
- `all` (optional): Set to "true" to get all products without pagination

**Examples:**
```
GET /api/products                    # Page 1, 15 items
GET /api/products?page=2             # Page 2, 15 items  
GET /api/products?page=1&per_page=20 # Page 1, 20 items
GET /api/products?all=true           # All products (no pagination)
```

**Paginated Response:**
```json
{
    "message": "Products retrieved successfully",
    "data": [
        {
            "id": 1,
            "name": "Product Name",
            "slug": "product-name",
            "category_id": 1,
            "business_id": 1,
            "image": "path/to/image.jpg",
            "description": "Product description",
            "buying_price": "100.00",
            "selling_price": "150.00",
            "stock_quantity": 50,
            "published": true
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
        "next_page_url": "http://your-app.com/api/products?page=2",
        "prev_page_url": null
    }
}
```

**All Products Response (when using ?all=true):**
```json
[
    {
        "id": 1,
        "name": "Product Name",
        "slug": "product-name",
        "category_id": 1,
        "business_id": 1,
        "image": "path/to/image.jpg",
        "description": "Product description",
        "buying_price": "100.00",
        "selling_price": "150.00",
        "stock_quantity": 50,
        "published": true
    }
]
```

**Empty Response:**
```json
{
    "message": "No products found",
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
    productsList.clear()
    loadProducts(page = 1, isRefresh = true)
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
            loadProducts(page = currentPage + 1, isRefresh = false)
        }
    }
})

fun loadProducts(page: Int, isRefresh: Boolean = false) {
    if (isRefresh) {
        swipeRefreshLayout.isRefreshing = true
    } else {
        isLoading = true
    }
    
    apiService.getProducts(page = page, perPage = 15)
        .enqueue(object : Callback<ProductResponse> {
            override fun onResponse(call: Call<ProductResponse>, response: Response<ProductResponse>) {
                if (response.isSuccessful) {
                    val productResponse = response.body()
                    
                    if (isRefresh) {
                        productsList.clear()
                        currentPage = 1
                    }
                    
                    productResponse?.data?.let { products ->
                        productsList.addAll(products)
                        adapter.notifyDataSetChanged()
                    }
                    
                    hasMorePages = productResponse?.pagination?.has_more_pages ?: false
                    if (!isRefresh) currentPage++
                }
                
                swipeRefreshLayout.isRefreshing = false
                isLoading = false
            }
            
            override fun onFailure(call: Call<ProductResponse>, t: Throwable) {
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
    products.removeAll()
    loadProducts(page: 1, isRefresh: true)
}

// Load more on scroll
func scrollViewDidScroll(_ scrollView: UIScrollView) {
    let offsetY = scrollView.contentOffset.y
    let contentHeight = scrollView.contentSize.height
    let height = scrollView.frame.size.height
    
    if offsetY > contentHeight - height * 1.5 && !isLoading && hasMorePages {
        loadProducts(page: currentPage + 1)
    }
}

func loadProducts(page: Int, isRefresh: Bool = false) {
    if !isRefresh {
        isLoading = true
    }
    
    APIService.shared.getProducts(page: page, perPage: 15) { [weak self] result in
        DispatchQueue.main.async {
            switch result {
            case .success(let response):
                if isRefresh {
                    self?.products.removeAll()
                    self?.currentPage = 1
                }
                
                self?.products.append(contentsOf: response.data)
                self?.hasMorePages = response.pagination.hasMorePages
                
                if !isRefresh {
                    self?.currentPage += 1
                }
                
                self?.tableView.reloadData()
                
            case .failure(let error):
                print("Error loading products: \(error)")
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

const ProductList = () => {
    const [products, setProducts] = useState([]);
    const [currentPage, setCurrentPage] = useState(1);
    const [isLoading, setIsLoading] = useState(false);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [hasMorePages, setHasMorePages] = useState(true);

    const loadProducts = async (page = 1, isRefresh = false) => {
        if (isRefresh) {
            setIsRefreshing(true);
        } else {
            setIsLoading(true);
        }

        try {
            const response = await fetch(
                `${API_BASE_URL}/products?page=${page}&per_page=15`,
                {
                    headers: {
                        'Authorization': `Bearer ${authToken}`,
                        'Content-Type': 'application/json',
                    },
                }
            );

            const data = await response.json();

            if (isRefresh) {
                setProducts(data.data);
                setCurrentPage(1);
            } else {
                setProducts(prev => [...prev, ...data.data]);
            }

            setHasMorePages(data.pagination.has_more_pages);
            if (!isRefresh) setCurrentPage(page);

        } catch (error) {
            console.error('Error loading products:', error);
        } finally {
            setIsLoading(false);
            setIsRefreshing(false);
        }
    };

    const handleRefresh = () => {
        loadProducts(1, true);
    };

    const handleLoadMore = () => {
        if (!isLoading && hasMorePages) {
            loadProducts(currentPage + 1);
        }
    };

    useEffect(() => {
        loadProducts(1);
    }, []);

    return (
        <FlatList
            data={products}
            keyExtractor={(item) => item.id.toString()}
            renderItem={({ item }) => <ProductItem product={item} />}
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

### 1. **Performance Optimized**
- Default 15 items per page for mobile optimization
- Maximum 100 items per page to prevent memory issues
- Ordered by creation date (newest first)

### 2. **Mobile-Friendly Response**
- `has_more_pages`: Boolean to easily check if more data exists
- `current_page` and `last_page` for progress indicators
- `total` count for showing "X of Y" indicators
- `next_page_url` and `prev_page_url` for easy navigation

### 3. **Error Handling**
- Graceful handling of invalid page numbers
- Consistent error response format
- Automatic parameter validation and sanitization

### 4. **Backward Compatibility**
- Pagination is now the default behavior for better performance
- Use `?all=true` parameter to get all products without pagination if needed
- Consistent endpoint (`/api/products`) with smart behavior based on parameters

## Authentication

Both endpoints require authentication via Sanctum token:

```
Authorization: Bearer {your-token}
```

## Rate Limiting

Consider implementing rate limiting on the pagination endpoint to prevent abuse:

```php
// In routes/api.php
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::get('/products-paginated', [ProductController::class, 'getPaginatedProducts']);
});
```

## Caching Recommendations

For better performance, consider implementing Redis caching:

```php
// In ProductRepository.php
public function getPaginatedProducts($perPage = 15, $page = 1)
{
    $cacheKey = "products_page_{$page}_per_{$perPage}";
    
    return Cache::remember($cacheKey, 300, function () use ($perPage, $page) {
        return $this->model->orderBy('created_at', 'desc')
                          ->paginate($perPage, ['*'], 'page', $page);
    });
}
```
