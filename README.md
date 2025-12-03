# MinecraftDatapackInstaller Plugin

A comprehensive example plugin created with FeatherPanel Plugin Manager that demonstrates all plugin capabilities.

## What it demonstrates
- **Dashboard Integration**: Shows how plugins can add pages to the user dashboard
- **Admin Integration**: Demonstrates admin panel integration with permissions
- **Server Integration**: Shows server-side functionality and monitoring
- **Database Migrations**: Creates plugin-specific tables with proper naming
- **Cron Jobs**: Runs scheduled tasks every hour
- **CLI Commands**: Provides command-line interface
- **Frontend Assets**: Includes CSS, JS, and sidebar configuration

## Files created
- `MinecraftDatapackInstaller.php` - Main plugin class
- `conf.yml` - Plugin configuration with enhanced schema
- `migrations/{timestamp}-create-minecraftdatapackinstaller-logs.sql` - Database migration with proper naming
- `cron/ExampleCron.php` - Hourly heartbeat cron job
- `Commands/MinecraftDatapackInstallerCommand.php` - CLI command
- `Frontend/index.css` - Plugin styling
- `Frontend/index.js` - Frontend JavaScript with modal system
- `Frontend/sidebar.json` - Sidebar configuration for all sections

## Sidebar Examples
### Dashboard Section
- **Overview**: Shows plugin data summary
- **Analytics**: Displays charts and statistics

### Admin Section
- **Settings**: Plugin configuration (requires admin.plugin.settings permission)
- **User Management**: Admin user tools (requires admin.plugin.users permission)

### Server Section
- **Server Logs**: View plugin-related logs
- **Scheduled Tasks**: Manage cron jobs and tasks

## How to use
1. **Dashboard**: Click sidebar buttons in dashboard to see user-facing modals
2. **Admin**: Access admin sections to see admin panel integration
3. **Server**: Check server sections for monitoring and logs
4. **Cron**: The cron job runs automatically every hour
5. **CLI**: Use the command: `php cli.php MinecraftDatapackInstaller`

## Migration Naming
Migrations use timestamp format `YYYY-MM-DD-HH.MM-description.sql` to avoid conflicts with other plugins and the main system.

This is a safe, comprehensive example that demonstrates all FeatherPanel plugin capabilities!