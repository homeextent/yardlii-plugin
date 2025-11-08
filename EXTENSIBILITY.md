Markdown



\\# YARDLII Core Extensibility Guide



This document outlines the hooks, filters, and interfaces available for developers to extend the YARDLII Core plugin, with a focus on the Trust \& Verification (TV) system.



\\#\\# 1\\. Trust \& Verification: Action Hooks



Action hooks allow you to execute your own code when a specific event occurs.



\\---



\\#\\#\\# yardlii\*\\\_tv\\\_\*decision\*\\\_made\*  

\*\\\[cite\\\_\*start\\]Fires immediately after a verification decision (approve, reject, reopen, resend) is successfully applied \\\[cite: 1019-1032\\].



\*\*\\\*\\\*Parameters:\\\*\\\*\*\*  

\\\* \\`int $request\\\_id\\`: The ID of the \\`verification\\\_request\\` post.  

\\\* \\`string $action\\`: The action taken ('approve', 'reject', 'reopen', 'resend').  

\\\* \\`int $user\\\_id\\`: The ID of the affected user.  

\\\* \\`int $admin\\\_id\\`: The ID of the admin who performed the action.



\*\*\\\*\\\*Example: Send a Slack notification on approval.\\\*\\\*\*\*  

\\`\\`\\`php  

add\\\_action('yardlii\\\_tv\\\_decision\\\_made', function( $request\\\_id, $action, $user\\\_id, $admin\\\_id ) {  

&nbsp;   if ( $action \\=== 'approve' ) {  

&nbsp;       $user \\= get\\\_userdata( $user\\\_id );  

&nbsp;       $admin \\= get\\\_userdata( $admin\\\_id );  

&nbsp;       // send\\\_to\\\_slack( "User {$user-\\>user\\\_login} was approved by {$admin-\\>user\\\_login}." );  

&nbsp;   }  

}, 10, 4);



---



\### \*\*yardlii\\\_tv\\\_after\\\_role\\\_change\*\*



Fires from the TvDecisionService \*after\* a user's role has been changed (or restored) due to a decision.



\*\*Parameters:\*\*



\* int $user\\\_id: The ID of the user whose role was changed.  

\* array $new\\\_roles: The list of new role slugs applied to the user.  

\* string $context: The context of the change ('decision' or 'reopen').



\*\*Example: Sync a role change to a 3rd-party system.\*\*



PHP



add\\\_action('yardlii\\\_tv\\\_after\\\_role\\\_change', function( $user\\\_id, $new\\\_roles, $context ) {  

&nbsp;   // update\\\_external\\\_crm( $user\\\_id, $new\\\_roles );  

}, 10, 3);



---



\## \*\*2\\. Trust \& Verification: Filter Hooks\*\*



Filter hooks allow you to modify data used by the plugin.



\### \*\*Email Filters\*\*



These filters, located in the Mailer class 1, allow you to customize all outgoing TV decision emails.



\* yardlii\\\_tv\\\_from\\\_name  

&nbsp; \* Modifies the "From" name on decision emails.  

&nbsp; \* ($from\\\_name, $context)  

\* yardlii\\\_tv\\\_from\\\_email  

&nbsp; \* Modifies the "From" email address on decision emails.  

&nbsp; \* ($from\\\_email, $context)  

\* yardlii\\\_tv\\\_from  

&nbsp; \* Modifies the entire "From" array (\\\['name' \\=\\> ..., 'email' \\=\\> ...\\]).  

&nbsp; \* ($from\\\_pair, $context)  

\* yardlii\\\_tv\\\_email\\\_recipients  

&nbsp; \* Modifies the final list of recipients (the To: address).  

&nbsp; \* ($recipients\\\_array, $context)  

\* yardlii\\\_tv\\\_email\\\_headers  

&nbsp; \* Modifies the array of email headers (e.g., to add a CC: or BCC:).  

&nbsp; \* ($headers\\\_array, $context)  

\* yardlii\\\_tv\\\_placeholder\\\_context  

&nbsp; \* Modifies the data used to render placeholders in email templates.  

&nbsp; \* ($context\\\_array)



\*\*Example: Add a BCC: to all decision emails.\*\*



PHP



add\\\_filter('yardlii\\\_tv\\\_email\\\_headers', function( $headers, $context ) {  

&nbsp;   $headers\\\[\\] \\= 'Bcc: compliance@example.com';  

&nbsp;   return $headers;  

}, 10, 2);



\### \*\*Provider \& Settings Filters\*\*



\* yardlii\\\_tv\\\_enabled\\\_providers  

&nbsp; \* Modifies the array of form providers (like WPUF or Elementor) to be loaded by the TvProviderRegistry. This is the hook to use for adding your own custom provider.  

&nbsp; \* ($providers\\\_array)  

\* yardlii\\\_tv\\\_form\\\_configs\\\_sanitized  

&nbsp; \* Modifies the 'Per-Form Configuration' data just before it's saved to the database.  

&nbsp; \* ($sanitized\\\_config\\\_array)



\## \*\*3\\. Trust \& Verification: Adding a New Form Provider\*\*



Thanks to the refactor in v3.7.0, the "Trust \& Verification" system can listen for form submissions from any plugin (like Gravity Forms, Contact Form 7, etc.).



To do this, you must:



1\. Create a new PHP class that implements the TvProviderInterface 2.



2\. Register your new class with the TvProviderRegistry 3.



---



\### \*\*Step 1: Implement the TvProviderInterface\*\*



The interface is simple. Your class \*\*must\*\* implement the TvProviderInterface and provide a single public method: registerHooks().



PHP



\\<?php  

// In your-plugin/includes/MyGravityFormProvider.php  

namespace MyPlugin\\\\Providers;



use Yardlii\\\\Core\\\\Features\\\\TrustVerification\\\\Providers\\\\TvProviderInterface;  

use Yardlii\\\\Core\\\\Features\\\\TrustVerification\\\\Requests\\\\CPT; // Use the CPT helper



class MyGravityFormProvider implements TvProviderInterface  

{  

&nbsp;   /\\\*\\\*  

&nbsp;    \\\* Registers all necessary hooks for the provider.  

&nbsp;    \\\*/  

&nbsp;   public function registerHooks(): void  

&nbsp;   {  

&nbsp;       // Use the hook from your chosen form plugin.  

&nbsp;       // For Gravity Forms, this is 'gform\\\_after\\\_submission'  

&nbsp;       add\\\_action('gform\\\_after\\\_submission', \\\[$this, 'handleSubmission'\\], 10, 2);  

&nbsp;   }



&nbsp;   /\\\*\\\*  

&nbsp;    \\\* This is your custom function that handles the form submission.  

&nbsp;    \\\*/  

&nbsp;   public function handleSubmission($entry, $form)  

&nbsp;   {  

&nbsp;       // 1\\. Check if this is the form you care about  

&nbsp;       // (e.g., check $form\\\['id'\\] or a specific field)  

&nbsp;       if ($form\\\['id'\\] \\!= 12) {  

&nbsp;           return;  

&nbsp;       }



&nbsp;       // 2\\. Get the User ID (e.g., from the logged-in user or a field)  

&nbsp;       $user\\\_id \\= get\\\_current\\\_user\\\_id();  

&nbsp;       if (\\!$user\\\_id) {  

&nbsp;           return; // Not a logged-in user  

&nbsp;       }  

&nbsp;         

&nbsp;       // 3\\. Get the "Form ID" string from your form settings.  

&nbsp;       // This MUST match the "Form ID" you set in the TV Config table.  

&nbsp;       // Here, we'll just use the Gravity Forms numeric ID.  

&nbsp;       $tv\\\_form\\\_id \\= (string) $form\\\['id'\\];



&nbsp;       // 4\\. Create the verification request post  

&nbsp;       // This is the core logic.  

&nbsp;       try {  

&nbsp;           // Check if a 'pending' request already exists for this user/form  

&nbsp;           $existing \\= get\\\_posts(\\\[  

&nbsp;               'post\\\_type'   \\=\\> CPT::POST\\\_TYPE,  

&nbsp;               'post\\\_status' \\=\\> 'vp\\\_pending',  

&nbsp;               'meta\\\_key'    \\=\\> '\\\_vp\\\_user\\\_id',  

&nbsp;               'meta\\\_value'  \\=\\> $user\\\_id,  

&nbsp;               'posts\\\_per\\\_page' \\=\\> 1,  

&nbsp;               'meta\\\_query' \\=\\> \\\[  

&nbsp;                   \\\[  

&nbsp;                       'key' \\=\\> '\\\_vp\\\_form\\\_id',  

&nbsp;                       'value' \\=\\> $tv\\\_form\\\_id,  

&nbsp;                   \\]  

&nbsp;               \\]  

&nbsp;           \\]);



&nbsp;           if ($existing) {  

&nbsp;               // Optional: Update existing post title  

&nbsp;               wp\\\_update\\\_post(\\\[  

&nbsp;                   'ID' \\=\\> $existing\\\[0\\]-\\>ID,  

&nbsp;                   'post\\\_title' \\=\\> 'Request updated on ' . gmdate('c'),  

&nbsp;               \\]);  

&nbsp;               return;  

&nbsp;           }



&nbsp;           // Create new request  

&nbsp;           $request\\\_id \\= wp\\\_insert\\\_post(\\\[  

&nbsp;               'post\\\_type'   \\=\\> CPT::POST\\\_TYPE,  

&nbsp;               'post\\\_status' \\=\\> 'vp\\\_pending',  

&nbsp;               'post\\\_title'  \\=\\> sprintf('Verification Request for User %d (Form %s)', $user\\\_id, $tv\\\_form\\\_id),  

&nbsp;               'post\\\_author' \\=\\> $user\\\_id,  

&nbsp;           \\]);



&nbsp;           if (is\\\_wp\\\_error($request\\\_id)) {  

&nbsp;               return; // Failed to create post  

&nbsp;           }



&nbsp;           // 5\\. Store the critical metadata  

&nbsp;           update\\\_post\\\_meta($request\\\_id, '\\\_vp\\\_user\\\_id', $user\\\_id);  

&nbsp;           update\\\_post\\\_meta($request\\\_id, '\\\_vp\\\_form\\\_id', $tv\\\_form\\\_id);



&nbsp;           // Optional: Store entry data, form data, etc.  

&nbsp;           // update\\\_post\\\_meta($request\\\_id, '\\\_my\\\_plugin\\\_entry\\\_id', $entry\\\['id'\\]);



&nbsp;       } catch (\\\\Throwable $e) {  

&nbsp;           // Handle error  

&nbsp;           error\\\_log('\\\[MyPlugin\\] Failed to create TV request: ' . $e\\-\\>getMessage());  

&nbsp;       }  

&nbsp;   }  

}



---



\### \*\*Step 2: Register Your Provider\*\*



In your plugin's main file, tap into the yardlii\\\_tv\\\_enabled\\\_providers filter. This tells the TvProviderRegistry to load your class 4.



PHP



\\<?php  

// In your-plugin/my-plugin.php



add\\\_filter('yardlii\\\_tv\\\_enabled\\\_providers', function ( $providers ) {  

&nbsp;     

&nbsp;   // 1\\. Make sure your class is loaded (e.g., via autoloader)  

&nbsp;   if ( \\! class\\\_exists('\\\\MyPlugin\\\\Providers\\\\MyGravityFormProvider') ) {  

&nbsp;       require\\\_once \\\_\\\_DIR\\\_\\\_ . '/includes/MyGravityFormProvider.php';  

&nbsp;   }



&nbsp;   // 2\\. Add your provider to the array  

&nbsp;   // The key 'gforms' is a unique string for your provider.  

&nbsp;   // The value is the fully-qualified class name.  

&nbsp;   $providers\\\['gforms'\\] \\= '\\\\MyPlugin\\\\Providers\\\\MyGravityFormProvider';



&nbsp;   return $providers;  

});





