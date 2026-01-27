// assets/js/main.js

/**
 * EliteWinnersWorldwide - Main JavaScript
 * Handles all interactive functionality for the website
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all components
    initMobileMenu();
    initServiceCards();
    initProductQuickView();
    initCart();
    initBookingForm();
    initTestimonialSlider();
    initModals();
    initRevealAnimations();
    initCurrentYear();
    
    console.log('EliteWinnersWorldwide website initialized');
});

/**
 * Mobile Menu Functionality
 */
function initMobileMenu() {
    const menuButton = document.querySelector('.mobile-menu-btn');
    const mobileNav = document.querySelector('.mobile-nav');
    
    if (!menuButton || !mobileNav) return;
    
    menuButton.addEventListener('click', function() {
        const isExpanded = menuButton.getAttribute('aria-expanded') === 'true';
        
        // Toggle menu visibility
        mobileNav.classList.toggle('hidden');
        menuButton.setAttribute('aria-expanded', !isExpanded);
        
        // Update icon
        const icon = menuButton.querySelector('svg');
        if (!isExpanded) {
            icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>';
        } else {
            icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>';
        }
    });
    
    // Close menu when clicking on links
    const mobileLinks = mobileNav.querySelectorAll('a');
    mobileLinks.forEach(link => {
        link.addEventListener('click', () => {
            mobileNav.classList.add('hidden');
            menuButton.setAttribute('aria-expanded', 'false');
            const icon = menuButton.querySelector('svg');
            icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>';
        });
    });
}

/**
 * Service Cards - Booking Modal Integration
 */
function initServiceCards() {
    const serviceButtons = document.querySelectorAll('.service-cta');
    const bookingModal = document.getElementById('booking-modal');
    const selectedServiceElement = document.getElementById('selected-service');
    
    if (!serviceButtons.length || !bookingModal) return;
    
    serviceButtons.forEach(button => {
        button.addEventListener('click', function() {
            const serviceType = this.getAttribute('data-service-type');
            
            // Set the selected service in the modal
            selectedServiceElement.textContent = serviceType;
            
            // Set the service type in the booking form
            const sessionTypeSelect = document.getElementById('session-type');
            if (sessionTypeSelect) {
                for (let i = 0; i < sessionTypeSelect.options.length; i++) {
                    if (sessionTypeSelect.options[i].value === serviceType) {
                        sessionTypeSelect.selectedIndex = i;
                        break;
                    }
                }
            }
            
            // Show the modal
            showModal(bookingModal);
        });
    });
}

/**
 * Product Quick View Modal
 */
function initProductQuickView() {
    const quickViewButtons = document.querySelectorAll('.product-quick-view');
    const quickViewModal = document.getElementById('quickview-modal');
    const quickViewContent = document.getElementById('quickview-content');
    
    if (!quickViewButtons.length || !quickViewModal || !quickViewContent) return;
    
    // Mock product data - in a real implementation, this would come from a database
    const products = {
        1: {
            name: 'Elite Training Soccer Ball',
            price: '$49.99',
            description: 'Professional match ball with enhanced durability and precision flight. Designed for elite training sessions and match play.',
            image: 'assets/images/product-1.jpg',
            features: ['FIFA Quality Pro certified', 'High-visibility design', 'Water-resistant construction', 'Aerodynamic precision']
        },
        2: {
            name: 'Agility Training Set',
            price: '$89.99',
            description: 'Complete agility kit with cones, ladder, and resistance bands. Perfect for improving footwork, speed, and coordination.',
            image: 'assets/images/product-2.jpg',
            features: ['12 agility cones', '10 rung speed ladder', '3 resistance bands', 'Carry bag included']
        },
        3: {
            name: 'Performance Jersey',
            price: '$39.99',
            description: 'Moisture-wicking jersey with advanced ventilation technology. Keeps you cool and dry during intense training sessions.',
            image: 'assets/images/product-3.jpg',
            features: ['Dri-FIT technology', 'Mesh ventilation panels', 'Lightweight fabric', 'Elastic cuffs']
        },
        4: {
            name: 'Training Program eBook',
            price: '$24.99',
            description: 'Comprehensive 12-week training program designed by professional coaches. Includes drills, exercises, and nutrition guidance.',
            image: 'assets/images/product-4.jpg',
            features: ['PDF and EPUB formats', 'Video demonstrations', 'Printable workout plans', 'Nutrition guide']
        }
    };
    
    quickViewButtons.forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            const product = products[productId];
            
            if (!product) return;
            
            // Populate the quick view modal
            quickViewContent.innerHTML = `
                <div class="quickview-image-container">
                    <img src="${product.image}" alt="${product.name}" class="quickview-image">
                </div>
                <div class="quickview-details">
                    <h3 class="quickview-title">${product.name}</h3>
                    <div class="quickview-price">${product.price}</div>
                    <p class="quickview-description">${product.description}</p>
                    <ul class="quickview-features">
                        ${product.features.map(feature => `<li>${feature}</li>`).join('')}
                    </ul>
                    <button class="product-add-to-cart btn-primary w-full mt-6" 
                            data-product-id="${productId}" 
                            data-product-name="${product.name}" 
                            data-product-price="${product.price.replace('$', '')}">
                        Add to Cart
                    </button>
                </div>
            `;
            
            // Add event listener to the Add to Cart button in the modal
            const addToCartButton = quickViewContent.querySelector('.product-add-to-cart');
            if (addToCartButton) {
                addToCartButton.addEventListener('click', function() {
                    addToCart(
                        this.getAttribute('data-product-id'),
                        this.getAttribute('data-product-name'),
                        parseFloat(this.getAttribute('data-product-price')),
                        1
                    );
                    
                    // Close the quick view modal
                    hideModal(quickViewModal);
                    
                    // Show a success message (could be enhanced with a toast notification)
                    alert(`${product.name} added to cart!`);
                });
            }
            
            // Show the modal
            showModal(quickViewModal);
        });
    });
}

/**
 * Shopping Cart Functionality
 */
function initCart() {
    const cartIcon = document.querySelector('.cart-icon');
    const cartSlideover = document.getElementById('cart-slideover');
    const cartItemsContainer = document.getElementById('cart-items');
    const cartSubtotalElement = document.getElementById('cart-subtotal-amount');
    const checkoutButton = document.getElementById('checkout-button');
    const cartCountElement = document.querySelector('.cart-count');
    
    if (!cartIcon || !cartSlideover) return;
    
    // Initialize cart from localStorage
    let cart = JSON.parse(localStorage.getItem('eww-cart')) || [];
    updateCartUI();
    
    // Open cart slideover
    cartIcon.addEventListener('click', function() {
        showSlideover(cartSlideover);
    });
    
    // Add to cart buttons
    const addToCartButtons = document.querySelectorAll('.product-add-to-cart');
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            const productName = this.getAttribute('data-product-name');
            const productPrice = parseFloat(this.getAttribute('data-product-price'));
            
            addToCart(productId, productName, productPrice, 1);
            
            // Show a success message (could be enhanced with a toast notification)
            alert(`${productName} added to cart!`);
        });
    });
    
    // Checkout button
    if (checkoutButton) {
        checkoutButton.addEventListener('click', function() {
            if (cart.length === 0) return;
            
            // TODO: Replace with actual backend endpoint
            console.log('Proceeding to checkout with items:', cart);
            
            // For demo purposes, we'll just show an alert
            alert('Redirecting to checkout... In a real implementation, this would connect to Stripe, PayPal, or another payment processor.');
            
            // Example fetch request (commented out as endpoint doesn't exist)
            /*
            fetch('/api/checkout', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ items: cart })
            })
            .then(response => response.json())
            .then(data => {
                // Handle response
                if (data.success) {
                    // Redirect to payment page
                    window.location.href = data.redirectUrl;
                } else {
                    alert('Checkout failed: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Checkout failed. Please try again.');
            });
            */
        });
    }
    
    /**
     * Add item to cart
     */
    function addToCart(productId, productName, productPrice, quantity) {
        // Check if product already exists in cart
        const existingItemIndex = cart.findIndex(item => item.id === productId);
        
        if (existingItemIndex >= 0) {
            // Update quantity
            cart[existingItemIndex].quantity += quantity;
        } else {
            // Add new item
            cart.push({
                id: productId,
                name: productName,
                price: productPrice,
                quantity: quantity
            });
        }
        
        // Save to localStorage
        localStorage.setItem('eww-cart', JSON.stringify(cart));
        
        // Update UI
        updateCartUI();
    }
    
    /**
     * Update cart UI
     */
    function updateCartUI() {
        // Update cart count
        const totalItems = cart.reduce((total, item) => total + item.quantity, 0);
        if (cartCountElement) {
            cartCountElement.textContent = totalItems;
            cartCountElement.style.display = totalItems > 0 ? 'flex' : 'none';
        }
        
        // Update cart items and subtotal
        if (cartItemsContainer && cartSubtotalElement && checkoutButton) {
            if (cart.length === 0) {
                cartItemsContainer.innerHTML = '<div class="empty-cart-message">Your cart is empty</div>';
                cartSubtotalElement.textContent = '$0.00';
                checkoutButton.disabled = true;
            } else {
                let subtotal = 0;
                cartItemsContainer.innerHTML = '';
                
                cart.forEach(item => {
                    const itemTotal = item.price * item.quantity;
                    subtotal += itemTotal;
                    
                    const cartItemElement = document.createElement('div');
                    cartItemElement.className = 'cart-item';
                    cartItemElement.innerHTML = `
                        <img src="assets/images/product-${item.id}.jpg" alt="${item.name}" class="cart-item-image">
                        <div class="cart-item-details">
                            <div class="cart-item-name">${item.name}</div>
                            <div class="cart-item-price">$${item.price.toFixed(2)}</div>
                            <div class="cart-item-quantity">
                                <button class="quantity-button decrease" data-product-id="${item.id}">-</button>
                                <input type="number" class="quantity-input" value="${item.quantity}" min="1" data-product-id="${item.id}">
                                <button class="quantity-button increase" data-product-id="${item.id}">+</button>
                                <button class="cart-item-remove" data-product-id="${item.id}">Remove</button>
                            </div>
                        </div>
                    `;
                    
                    cartItemsContainer.appendChild(cartItemElement);
                });
                
                // Add event listeners to quantity buttons
                const decreaseButtons = cartItemsContainer.querySelectorAll('.quantity-button.decrease');
                const increaseButtons = cartItemsContainer.querySelectorAll('.quantity-button.increase');
                const quantityInputs = cartItemsContainer.querySelectorAll('.quantity-input');
                const removeButtons = cartItemsContainer.querySelectorAll('.cart-item-remove');
                
                decreaseButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const productId = this.getAttribute('data-product-id');
                        updateQuantity(productId, -1);
                    });
                });
                
                increaseButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const productId = this.getAttribute('data-product-id');
                        updateQuantity(productId, 1);
                    });
                });
                
                quantityInputs.forEach(input => {
                    input.addEventListener('change', function() {
                        const productId = this.getAttribute('data-product-id');
                        const newQuantity = parseInt(this.value);
                        
                        if (newQuantity < 1) {
                            this.value = 1;
                            updateQuantity(productId, 0, 1);
                        } else {
                            const item = cart.find(item => item.id === productId);
                            if (item) {
                                const quantityChange = newQuantity - item.quantity;
                                updateQuantity(productId, quantityChange);
                            }
                        }
                    });
                });
                
                removeButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const productId = this.getAttribute('data-product-id');
                        removeFromCart(productId);
                    });
                });
                
                cartSubtotalElement.textContent = `$${subtotal.toFixed(2)}`;
                checkoutButton.disabled = false;
            }
        }
    }
    
    /**
     * Update item quantity in cart
     */
    function updateQuantity(productId, change, setQuantity = null) {
        const itemIndex = cart.findIndex(item => item.id === productId);
        
        if (itemIndex >= 0) {
            if (setQuantity !== null) {
                cart[itemIndex].quantity = setQuantity;
            } else {
                cart[itemIndex].quantity += change;
            }
            
            // Remove item if quantity is 0 or less
            if (cart[itemIndex].quantity <= 0) {
                cart.splice(itemIndex, 1);
            }
            
            // Save to localStorage and update UI
            localStorage.setItem('eww-cart', JSON.stringify(cart));
            updateCartUI();
        }
    }
    
    /**
     * Remove item from cart
     */
    function removeFromCart(productId) {
        cart = cart.filter(item => item.id !== productId);
        
        // Save to localStorage and update UI
        localStorage.setItem('eww-cart', JSON.stringify(cart));
        updateCartUI();
    }
}

/**
 * Booking Form Functionality
 */
function initBookingForm() {
    const bookingForm = document.getElementById('booking-form');
    
    if (!bookingForm) return;
    
    // Load saved form data from localStorage if available
    loadFormData();
    
    // Save form data on input change
    const formInputs = bookingForm.querySelectorAll('input, select, textarea');
    formInputs.forEach(input => {
        input.addEventListener('input', saveFormData);
    });
    
    // Form submission
    bookingForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validate form
        if (validateForm()) {
            // Show loading state
            const submitButton = this.querySelector('button[type="submit"]');
            const originalText = submitButton.textContent;
            submitButton.textContent = 'Processing...';
            submitButton.disabled = true;
            
            // Get form data
            const formData = {
                playerName: document.getElementById('player-name').value,
                playerAge: document.getElementById('player-age').value,
                playerEmail: document.getElementById('player-email').value,
                playerPhone: document.getElementById('player-phone').value,
                sessionType: document.getElementById('session-type').value,
                preferredDate: document.getElementById('preferred-date').value,
                preferredTime: document.getElementById('preferred-time').value,
                notes: document.getElementById('notes').value
            };
            
            // TODO: Replace with actual backend endpoint
            console.log('Submitting booking form:', formData);
            
            // Simulate API request
            setTimeout(() => {
                // Show success message
                showFormMessage('Booking request submitted successfully! We will contact you within 24 hours to confirm your session.', 'success');
                
                // Clear form and localStorage
                bookingForm.reset();
                localStorage.removeItem('eww-booking-form');
                
                // Reset button
                submitButton.textContent = originalText;
                submitButton.disabled = false;
            }, 1500);
            
            // Example fetch request (commented out as endpoint doesn't exist)
            /*
            fetch('/api/book', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showFormMessage('Booking request submitted successfully! We will contact you within 24 hours to confirm your session.', 'success');
                    bookingForm.reset();
                    localStorage.removeItem('eww-booking-form');
                } else {
                    showFormMessage('There was an error submitting your booking. Please try again.', 'error');
                    console.error('Booking error:', data.message);
                }
            })
            .catch(error => {
                showFormMessage('There was an error submitting your booking. Please try again.', 'error');
                console.error('Error:', error);
            })
            .finally(() => {
                submitButton.textContent = originalText;
                submitButton.disabled = false;
            });
            */
        }
    });
    
    /**
     * Validate form fields
     */
    function validateForm() {
        let isValid = true;
        
        // Reset error messages
        const errorElements = document.querySelectorAll('.form-error');
        errorElements.forEach(element => {
            element.classList.add('hidden');
            element.textContent = '';
        });
        
        // Validate each field
        const fields = [
            { id: 'player-name', name: 'Player Name', validate: value => value.trim().length > 0 },
            { id: 'player-age', name: 'Age', validate: value => {
                const age = parseInt(value);
                return !isNaN(age) && age >= 8 && age <= 99;
            }},
            { id: 'player-email', name: 'Email', validate: value => {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(value);
            }},
            { id: 'player-phone', name: 'Phone', validate: value => value.trim().length > 0 },
            { id: 'session-type', name: 'Session Type', validate: value => value !== '' },
            { id: 'preferred-date', name: 'Preferred Date', validate: value => value !== '' },
            { id: 'preferred-time', name: 'Preferred Time', validate: value => value !== '' }
        ];
        
        fields.forEach(field => {
            const input = document.getElementById(field.id);
            const errorElement = document.getElementById(`${field.id}-error`);
            
            if (!input || !errorElement) return;
            
            if (!field.validate(input.value)) {
                errorElement.textContent = `Please enter a valid ${field.name.toLowerCase()}`;
                errorElement.classList.remove('hidden');
                input.setAttribute('aria-invalid', 'true');
                isValid = false;
            } else {
                input.setAttribute('aria-invalid', 'false');
            }
        });
        
        return isValid;
    }
    
    /**
     * Show form message
     */
    function showFormMessage(message, type) {
        const messageElement = document.getElementById('form-message');
        
        if (!messageElement) return;
        
        messageElement.textContent = message;
        messageElement.classList.remove('hidden', 'success', 'error');
        messageElement.classList.add(type);
        
        // Scroll to message
        messageElement.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            messageElement.classList.add('hidden');
        }, 5000);
    }
    
    /**
     * Save form data to localStorage
     */
    function saveFormData() {
        const formData = {
            playerName: document.getElementById('player-name').value,
            playerAge: document.getElementById('player-age').value,
            playerEmail: document.getElementById('player-email').value,
            playerPhone: document.getElementById('player-phone').value,
            sessionType: document.getElementById('session-type').value,
            preferredDate: document.getElementById('preferred-date').value,
            preferredTime: document.getElementById('preferred-time').value,
            notes: document.getElementById('notes').value
        };
        
        localStorage.setItem('eww-booking-form', JSON.stringify(formData));
    }
    
    /**
     * Load form data from localStorage
     */
    function loadFormData() {
        const savedData = localStorage.getItem('eww-booking-form');
        
        if (savedData) {
            try {
                const formData = JSON.parse(savedData);
                
                for (const key in formData) {
                    const element = document.getElementById(key);
                    if (element && formData[key]) {
                        element.value = formData[key];
                    }
                }
            } catch (e) {
                console.error('Error loading saved form data:', e);
            }
        }
    }
}

/**
 * Testimonial Slider
 */
function initTestimonialSlider() {
    const testimonialSlides = document.querySelectorAll('.testimonial-slide');
    const prevButton = document.querySelector('.testimonial-prev');
    const nextButton = document.querySelector('.testimonial-next');
    const dots = document.querySelectorAll('.testimonial-dot');
    
    if (!testimonialSlides.length || !prevButton || !nextButton || !dots.length) return;
    
    let currentSlide = 0;
    let autoplayInterval;
    
    // Set up event listeners
    prevButton.addEventListener('click', showPreviousSlide);
    nextButton.addEventListener('click', showNextSlide);
    
    dots.forEach((dot, index) => {
        dot.addEventListener('click', () => showSlide(index));
    });
    
    // Pause autoplay when interacting with slider
    const slider = document.querySelector('.testimonial-slider');
    if (slider) {
        slider.addEventListener('mouseenter', pauseAutoplay);
        slider.addEventListener('focusin', pauseAutoplay);
        slider.addEventListener('mouseleave', startAutoplay);
        slider.addEventListener('focusout', startAutoplay);
    }
    
    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowLeft') {
            showPreviousSlide();
        } else if (e.key === 'ArrowRight') {
            showNextSlide();
        }
    });
    
    // Start autoplay if reduced motion is not preferred
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        startAutoplay();
    }
    
    /**
     * Show specific slide
     */
    function showSlide(index) {
        // Hide all slides
        testimonialSlides.forEach(slide => {
            slide.classList.remove('active');
        });
        
        // Update dots
        dots.forEach(dot => {
            dot.classList.remove('active');
        });
        
        // Show selected slide
        testimonialSlides[index].classList.add('active');
        dots[index].classList.add('active');
        
        currentSlide = index;
    }
    
    /**
     * Show next slide
     */
    function showNextSlide() {
        let nextIndex = currentSlide + 1;
        if (nextIndex >= testimonialSlides.length) {
            nextIndex = 0;
        }
        showSlide(nextIndex);
    }
    
    /**
     * Show previous slide
     */
    function showPreviousSlide() {
        let prevIndex = currentSlide - 1;
        if (prevIndex < 0) {
            prevIndex = testimonialSlides.length - 1;
        }
        showSlide(prevIndex);
    }
    
    /**
     * Start autoplay
     */
    function startAutoplay() {
        if (autoplayInterval) clearInterval(autoplayInterval);
        autoplayInterval = setInterval(showNextSlide, 5000);
    }
    
    /**
     * Pause autoplay
     */
    function pauseAutoplay() {
        if (autoplayInterval) {
            clearInterval(autoplayInterval);
            autoplayInterval = null;
        }
    }
}

/**
 * Modal and Slideover Management
 */
function initModals() {
    const modals = document.querySelectorAll('.modal, .slideover');
    
    if (!modals.length) return;
    
    // Close modals when clicking outside
    modals.forEach(modal => {
        const overlay = modal.querySelector('[data-close-modal], [data-close-slideover]');
        const closeButton = modal.querySelector('.modal-close, .slideover-close');
        
        if (overlay) {
            overlay.addEventListener('click', function() {
                if (modal.classList.contains('modal')) {
                    hideModal(modal);
                } else {
                    hideSlideover(modal);
                }
            });
        }
        
        if (closeButton) {
            closeButton.addEventListener('click', function() {
                if (modal.classList.contains('modal')) {
                    hideModal(modal);
                } else {
                    hideSlideover(modal);
                }
            });
        }
    });
    
    // Close modals with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const openModal = document.querySelector('.modal.active');
            const openSlideover = document.querySelector('.slideover.active');
            
            if (openModal) {
                hideModal(openModal);
            }
            
            if (openSlideover) {
                hideSlideover(openSlideover);
            }
        }
    });
}

/**
 * Show modal
 */
function showModal(modal) {
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    
    // Focus on first interactive element
    const focusableElement = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    if (focusableElement) {
        focusableElement.focus();
    }
}

/**
 * Hide modal
 */
function hideModal(modal) {
    modal.classList.remove('active');
    document.body.style.overflow = '';
}

/**
 * Show slideover
 */
function showSlideover(slideover) {
    slideover.classList.add('active');
    document.body.style.overflow = 'hidden';
    
    // Focus on first interactive element
    const focusableElement = slideover.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    if (focusableElement) {
        focusableElement.focus();
    }
}

/**
 * Hide slideover
 */
function hideSlideover(slideover) {
    slideover.classList.remove('active');
    document.body.style.overflow = '';
}

/**
 * Reveal animations on scroll
 */
function initRevealAnimations() {
    const revealElements = document.querySelectorAll('.reveal');
    
    if (!revealElements.length) return;
    
    // Create Intersection Observer
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('active');
                
                // Stop observing after animation
                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    });
    
    // Observe all reveal elements
    revealElements.forEach(element => {
        observer.observe(element);
    });
}

/**
 * Set current year in footer
 */
function initCurrentYear() {
    const yearElement = document.getElementById('current-year');
    if (yearElement) {
        yearElement.textContent = new Date().getFullYear();
    }
}

// Add reveal class to service cards and product cards for animation
document.querySelectorAll('.service-card, .product-card').forEach(card => {
    card.classList.add('reveal');
});