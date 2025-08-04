# temp-account-manage

## My basic idea so far

#### **Phase 1: Initial User Request**

1. A user navigates to the login page. 
    
2. The PHP script checks for authentication parameters (`code` or `error` in the URL). If none are found, it initiates the OAuth2 flow.
    
3. The script generates a unique `state` parameter (using `session_id()`) for Cross-Site Request Forgery (CSRF) protection and redirects the user to the Microsoft authorization endpoint.
    

#### **Phase 2: Microsoft Authentication**

1. The user is on the Microsoft login page and enters their credentials (and performs MFA if required).
    
2. Microsoft validates the credentials and checks if the user has previously consented to the application's required permissions.
    
3. If successful, Microsoft generates an authorization code and redirects the user back to our application's `redirect_uri`.
    

#### **Phase 3: Authorization Code Exchange**

1. Our script receives the `authorization_code` from the URL.
    
2. It first validates that the `state` parameter matches the user's `session_id()` to prevent CSRF attacks.
    
3. The script then sends a secure POST request to Microsoft's token endpoint to exchange the authorization code for an **access token**. This request includes our `client_id` and `client_secret`.
    

#### **Phase 4: User Information Retrieval**

1. Using the newly acquired `access_token`, the script makes a GET request to the **Microsoft Graph API** (`https://graph.microsoft.com/v1.0/me`).
    
2. The API returns a JSON object containing the user's profile information, including their display name and email address.
    

#### **Phase 5: Local Authorization Check**

1. The script extracts the user's email address from the Graph API response.
    
2. It then searches our local `Users.csv` file to see if that email address is listed.
    
3. If a match is found, the user's access level (`admin`, `user`, etc.) is retrieved from the CSV file.
    

#### **Phase 6: Session Management and Redirection**

- **If Authorized:**
    
    1. The script sets several `$_SESSION` variables (`USER`, `EMAIL`, `Access_Level`, `loggedIn`).
        
    2. The user is redirected to the application's home page, now fully authenticated and authorized.
        
- **If Unauthorized:**
    
    1. Any existing session data is cleared.
        
    2. Access to the application is denied, and an error is returned.
        
