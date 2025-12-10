<style>
:root {
    --secondary-color: #00cec9;
}

.modern-footer {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    color: #ffffff;
    margin-top: 100px;
    padding: 60px 0 20px;
    position: relative;
    overflow: hidden;
}

.modern-footer::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
}

.footer-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 50px;
}

.footer-content {
    display: grid;
    grid-template-columns: 1.5fr 1fr 1fr;
    gap: 60px;
    margin-bottom: 40px;
}

.footer-brand {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.footer-logo {
    width: 120px;
    height: auto;
    margin-bottom: 10px;
    filter: brightness(0) invert(1);
}

.footer-brand h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #ffffff;
    margin: 0;
}

.footer-brand p {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.95rem;
    margin: 0;
}

.footer-links h4,
.footer-info h4 {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 20px;
    color: #ffffff;
}

.footer-links ul,
.footer-info ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.footer-links li,
.footer-info li {
    margin-bottom: 12px;
}

.footer-links a {
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
    transition: color 0.3s, padding-left 0.3s;
    display: inline-block;
}

.footer-links a:hover {
    color: #ffffff;
    padding-left: 5px;
}

.footer-info li {
    color: rgba(255, 255, 255, 0.7);
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.95rem;
}

.footer-info i {
    color: var(--secondary-color);
    width: 18px;
}

.footer-bottom {
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding-top: 30px;
    text-align: center;
}

.footer-bottom p {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.9rem;
    margin: 0;
}

@media (max-width: 768px) {
    .footer-container {
        padding: 0 20px;
    }

    .footer-content {
        grid-template-columns: 1fr;
        gap: 40px;
    }

    .footer-logo {
        width: 100px;
    }
}
</style>

<footer class="modern-footer">
    <div class="footer-container">
        <div class="footer-content">
            <div class="footer-brand">
                <img src="../public/rcmp-white.png" alt="UniKL RCMP Logo" class="footer-logo">
                <h3>UniKL Royal College of Medicine Perak</h3>
                <p>Dedicated to providing comprehensive IT services, infrastructure management, and innovative digital solutions for the institution.</p>
            </div>
            
            <div class="footer-links">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="#home">Home</a></li>
                    <li><a href="#features">Features</a></li>
                    <li><a href="#about">About</a></li>
                    <li><a href="#contact">Contact</a></li>
                </ul>
            </div>
            
            <div class="footer-info">
                <h4>Contact</h4>
                <ul>
                    <li><i class="fa-solid fa-envelope" style="color: #fff;"></i> it@unikl.edu.my</li>
                    <li><i class="fa-solid fa-phone" style="color: #fff;"></i> +605 243 2635</li>
                    <li><i class="fa-solid fa-location-dot" style="color: #fff;"></i> Perak, Malaysia</li>
                </ul>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; <?php echo date("Y"); ?> UniKL RCMP IT Department. All rights reserved.</p>
        </div>
    </div>
</footer>

