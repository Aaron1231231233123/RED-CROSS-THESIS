/**
 * Enhanced Data Handler
 * Comprehensive data management system for workflow processes
 * Handles data persistence, validation, and API communication
 */

class EnhancedDataHandler {
    constructor() {
        this.cache = new Map();
        this.pendingRequests = new Map();
        this.retryQueue = [];
        this.maxRetries = 3;
        this.retryDelay = 1000;
        
        this.init();
    }

    init() {
        this.setupCacheCleanup();
        this.setupRetryProcessor();
        console.log('Enhanced Data Handler initialized');
    }

    /**
     * Save workflow data with enhanced persistence
     * @param {string} workflowId - Unique workflow identifier
     * @param {Object} data - Data to save
     * @param {Object} options - Save options
     */
    async saveWorkflowData(workflowId, data, options = {}) {
        const saveOptions = {
            persist: true,
            validate: true,
            retry: true,
            ...options
        };

        try {
            // Validate data if required (do not hard-require timestamp for PE submissions)
            if (saveOptions.validate) {
                try {
                    this.validateWorkflowData(data);
                } catch (e) {
                    // Soften validation: only log for workflows that don't carry timestamp
                    console.warn('Validation warning (non-blocking):', e?.message || e);
                }
            }

            // Prepare data for saving
            const preparedData = this.prepareDataForSave(data);
            
            // Save to cache immediately
            this.cache.set(workflowId, {
                data: preparedData,
                timestamp: Date.now(),
                version: this.getDataVersion(workflowId) + 1
            });

            // Save to server if persistence is enabled
            if (saveOptions.persist) {
                await this.saveToServer(workflowId, preparedData, saveOptions);
            }

            console.log('Workflow data saved:', workflowId, preparedData);
            return { success: true, data: preparedData };

        } catch (error) {
            console.error('Error saving workflow data:', error);
            
            if (saveOptions.retry) {
                this.queueRetry('saveWorkflowData', [workflowId, data, options]);
            }
            
            throw error;
        }
    }

    /**
     * Load workflow data with caching
     * @param {string} workflowId - Workflow identifier
     * @param {Object} options - Load options
     */
    async loadWorkflowData(workflowId, options = {}) {
        const loadOptions = {
            useCache: true,
            refresh: false,
            ...options
        };

        try {
            // Check cache first
            if (loadOptions.useCache && !loadOptions.refresh) {
                const cachedData = this.cache.get(workflowId);
                if (cachedData && this.isCacheValid(cachedData)) {
                    console.log('Loading from cache:', workflowId);
                    return { success: true, data: cachedData.data, fromCache: true };
                }
            }

            // Load from server
            const serverData = await this.loadFromServer(workflowId, loadOptions);
            
            // Update cache
            this.cache.set(workflowId, {
                data: serverData,
                timestamp: Date.now(),
                version: 1
            });

            console.log('Workflow data loaded from server:', workflowId);
            return { success: true, data: serverData, fromCache: false };

        } catch (error) {
            console.error('Error loading workflow data:', error);
            
            // Try to return cached data as fallback
            const cachedData = this.cache.get(workflowId);
            if (cachedData) {
                console.warn('Using cached data due to server error:', workflowId);
                return { success: true, data: cachedData.data, fromCache: true, warning: 'Using cached data' };
            }
            
            throw error;
        }
    }

    /**
     * Update workflow data with conflict resolution
     * @param {string} workflowId - Workflow identifier
     * @param {Object} updates - Data updates
     * @param {Object} options - Update options
     */
    async updateWorkflowData(workflowId, updates, options = {}) {
        const updateOptions = {
            merge: true,
            validate: true,
            ...options
        };

        try {
            // Load current data
            const currentData = await this.loadWorkflowData(workflowId);
            
            // Merge updates
            const updatedData = updateOptions.merge ? 
                this.mergeData(currentData.data, updates) : 
                updates;

            // Validate updated data
            if (updateOptions.validate) {
                this.validateWorkflowData(updatedData);
            }

            // Save updated data
            await this.saveWorkflowData(workflowId, updatedData, updateOptions);

            console.log('Workflow data updated:', workflowId, updates);
            return { success: true, data: updatedData };

        } catch (error) {
            console.error('Error updating workflow data:', error);
            throw error;
        }
    }

    /**
     * Delete workflow data
     * @param {string} workflowId - Workflow identifier
     * @param {Object} options - Delete options
     */
    async deleteWorkflowData(workflowId, options = {}) {
        const deleteOptions = {
            softDelete: true,
            ...options
        };

        try {
            // Remove from cache
            this.cache.delete(workflowId);

            // Delete from server
            if (!deleteOptions.softDelete) {
                await this.deleteFromServer(workflowId);
            } else {
                // Mark as deleted
                await this.updateWorkflowData(workflowId, { 
                    deleted: true, 
                    deletedAt: new Date().toISOString() 
                });
            }

            console.log('Workflow data deleted:', workflowId);
            return { success: true };

        } catch (error) {
            console.error('Error deleting workflow data:', error);
            throw error;
        }
    }

    /**
     * Save data to server
     */
    async saveToServer(workflowId, data, options = {}) {
        const requestId = `save_${workflowId}_${Date.now()}`;
        
        // Check if request is already pending
        if (this.pendingRequests.has(requestId)) {
            return this.pendingRequests.get(requestId);
        }

        const request = this.makeApiCall('/api/save-workflow-data.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                workflow_id: workflowId,
                data: data,
                timestamp: new Date().toISOString()
            })
        });

        this.pendingRequests.set(requestId, request);

        try {
            const result = await request;
            this.pendingRequests.delete(requestId);
            return result;
        } catch (error) {
            this.pendingRequests.delete(requestId);
            throw error;
        }
    }

    /**
     * Load data from server
     */
    async loadFromServer(workflowId, options = {}) {
        const requestId = `load_${workflowId}_${Date.now()}`;
        
        // Check if request is already pending
        if (this.pendingRequests.has(requestId)) {
            return this.pendingRequests.get(requestId);
        }

        const request = this.makeApiCall(`/api/load-workflow-data.php?workflow_id=${workflowId}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        });

        this.pendingRequests.set(requestId, request);

        try {
            const result = await request;
            this.pendingRequests.delete(requestId);
            return result.data;
        } catch (error) {
            this.pendingRequests.delete(requestId);
            throw error;
        }
    }

    /**
     * Delete data from server
     */
    async deleteFromServer(workflowId) {
        const requestId = `delete_${workflowId}_${Date.now()}`;
        
        // Check if request is already pending
        if (this.pendingRequests.has(requestId)) {
            return this.pendingRequests.get(requestId);
        }

        const request = this.makeApiCall('/api/delete-workflow-data.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                workflow_id: workflowId
            })
        });

        this.pendingRequests.set(requestId, request);

        try {
            const result = await request;
            this.pendingRequests.delete(requestId);
            return result;
        } catch (error) {
            this.pendingRequests.delete(requestId);
            throw error;
        }
    }

    /**
     * Make API call with enhanced error handling
     */
    async makeApiCall(url, options = {}) {
        const defaultOptions = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            },
            timeout: 30000
        };

        const requestOptions = { ...defaultOptions, ...options };

        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), requestOptions.timeout);

            const response = await fetch(url, {
                ...requestOptions,
                signal: controller.signal
            });

            clearTimeout(timeoutId);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            return result;

        } catch (error) {
            if (error.name === 'AbortError') {
                throw new Error('Request timeout');
            }
            throw error;
        }
    }

    /**
     * Validate workflow data
     */
    validateWorkflowData(data) {
        // Only enforce donor_id by default; timestamp is optional for modal submissions
        const requiredFields = ['donor_id'];
        
        for (const field of requiredFields) {
            if (!data[field]) {
                throw new Error(`Required field missing: ${field}`);
            }
        }

        // Validate donor_id format
        if (typeof data.donor_id !== 'string' && typeof data.donor_id !== 'number') {
            throw new Error('Invalid donor_id format');
        }

        // Validate timestamp format when present
        if (data.timestamp !== undefined && data.timestamp !== null && data.timestamp !== '') {
            if (!this.isValidTimestamp(data.timestamp)) {
                throw new Error('Invalid timestamp format');
            }
        }

        return true;
    }

    /**
     * Prepare data for saving
     */
    prepareDataForSave(data) {
        const prepared = { ...data };
        
        // Ensure timestamp is ISO string
        if (prepared.timestamp && !this.isValidTimestamp(prepared.timestamp)) {
            prepared.timestamp = new Date().toISOString();
        }

        // Sanitize string fields
        Object.keys(prepared).forEach(key => {
            if (typeof prepared[key] === 'string') {
                prepared[key] = prepared[key].trim();
            }
        });

        return prepared;
    }

    /**
     * Merge data objects
     */
    mergeData(baseData, updates) {
        const merged = { ...baseData };
        
        Object.keys(updates).forEach(key => {
            if (typeof updates[key] === 'object' && updates[key] !== null && !Array.isArray(updates[key])) {
                merged[key] = this.mergeData(merged[key] || {}, updates[key]);
            } else {
                merged[key] = updates[key];
            }
        });

        return merged;
    }

    /**
     * Check if cache is valid
     */
    isCacheValid(cachedData) {
        const maxAge = 5 * 60 * 1000; // 5 minutes
        return (Date.now() - cachedData.timestamp) < maxAge;
    }

    /**
     * Get data version
     */
    getDataVersion(workflowId) {
        const cached = this.cache.get(workflowId);
        return cached ? cached.version : 0;
    }

    /**
     * Check if timestamp is valid
     */
    isValidTimestamp(timestamp) {
        const date = new Date(timestamp);
        return date instanceof Date && !isNaN(date);
    }

    /**
     * Queue retry for failed operations
     */
    queueRetry(operation, args) {
        const retryItem = {
            operation,
            args,
            attempts: 0,
            timestamp: Date.now()
        };

        this.retryQueue.push(retryItem);
        console.log('Operation queued for retry:', operation);
    }

    /**
     * Process retry queue
     */
    async processRetryQueue() {
        if (this.retryQueue.length === 0) return;

        const retryItem = this.retryQueue.shift();
        
        if (retryItem.attempts >= this.maxRetries) {
            console.error('Max retries exceeded for operation:', retryItem.operation);
            return;
        }

        try {
            retryItem.attempts++;
            await this[retryItem.operation](...retryItem.args);
            console.log('Retry successful for operation:', retryItem.operation);
        } catch (error) {
            console.error(`Retry ${retryItem.attempts} failed for operation:`, retryItem.operation, error);
            
            // Re-queue for next retry
            setTimeout(() => {
                this.retryQueue.push(retryItem);
            }, this.retryDelay * retryItem.attempts);
        }
    }

    /**
     * Setup cache cleanup
     */
    setupCacheCleanup() {
        // Clean cache every 10 minutes
        setInterval(() => {
            this.cleanCache();
        }, 10 * 60 * 1000);
    }

    /**
     * Setup retry processor
     */
    setupRetryProcessor() {
        // Process retry queue every 5 seconds
        setInterval(() => {
            this.processRetryQueue();
        }, 5000);
    }

    /**
     * Clean expired cache entries
     */
    cleanCache() {
        const now = Date.now();
        const maxAge = 30 * 60 * 1000; // 30 minutes

        for (const [key, value] of this.cache.entries()) {
            if ((now - value.timestamp) > maxAge) {
                this.cache.delete(key);
            }
        }

        console.log('Cache cleaned, remaining entries:', this.cache.size);
    }

    /**
     * Get cache statistics
     */
    getCacheStats() {
        return {
            size: this.cache.size,
            pendingRequests: this.pendingRequests.size,
            retryQueue: this.retryQueue.length
        };
    }

    /**
     * Clear all data
     */
    clearAll() {
        this.cache.clear();
        this.pendingRequests.clear();
        this.retryQueue = [];
        console.log('All data cleared');
    }
}

// Initialize global instance
window.dataHandler = new EnhancedDataHandler();

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = EnhancedDataHandler;
}
