#!/bin/bash

# CLI Client Access Creator
# Interactive mode by default, or create default access with full permissions
# @author wasilij.de
# @version 1.0
# @date 2025-06-06

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP_SCRIPT="$SCRIPT_DIR/cli-create-client-access.php"

# Check if PHP script exists
if [ ! -f "$PHP_SCRIPT" ]; then
    echo "Error: PHP script not found: $PHP_SCRIPT"
    exit 1
fi

# Function to validate email
validate_email() {
    local email="$1"
    if [[ -z "$email" ]]; then
        return 0  # Empty is allowed
    fi
    
    if [[ "$email" =~ ^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$ ]]; then
        return 0
    else
        return 1
    fi
}

# Function to show usage
show_usage() {
    echo "Usage: $0 [allowed_from] [allowed_to] [allowed_hosts] [description]"
    echo
    echo "If no parameters provided:"
    echo "  - Interactive mode will start"
    echo "  - Or create default access with full permissions"
    echo
    echo "Parameters:"
    echo "  allowed_from   : Sender email restriction (optional)"
    echo "  allowed_to     : Recipient restriction (* = all, comma-separated)"
    echo "  allowed_hosts  : Allowed hosts/IPs (comma-separated)"
    echo "  description    : Description for this client access (optional)"
    echo
    echo "Examples:"
    echo "  $0                                   # Interactive mode"
    echo "  $0 '' '' '' ''                       # Default access (full permissions)"
    echo "  $0 sender@example.com '*' '' 'Main App'  # Restrict sender only with description"
    echo "  $0 '' 'user@example.com' '' 'Reports'    # Restrict recipient only with description"
}

# Check for help
if [[ "$1" == "-h" || "$1" == "--help" ]]; then
    show_usage
    exit 0
fi

# If parameters provided, use them directly
if [[ $# -gt 0 ]]; then
    allowed_from="$1"
    allowed_to="$2"
    allowed_hosts="$3"
    description="$4"

    # Validate sender email if provided
    if ! validate_email "$allowed_from"; then
        echo "Error: Invalid sender email address: $allowed_from"
        exit 1
    fi

    echo "Creating client access with provided parameters..."
    php "$PHP_SCRIPT" "$allowed_from" "$allowed_to" "$allowed_hosts" "$description"
    exit $?
fi

# Interactive mode
echo "=== Client Access Generator ==="
echo

# Ask for creating default or interactive
echo "Choose option:"
echo "1) Create default access (full permissions)"
echo "2) Interactive setup"
echo -n "Enter choice [1-2]: "
read -r choice

case $choice in
    1)
        echo
        echo "Creating default client access with full permissions..."
        php "$PHP_SCRIPT" '' '' '' ''
        ;;
    2)
        echo
        echo "=== Interactive Setup ==="
        echo

        # Get sender email
        while true; do
            echo -n "Sender Email (optional, press Enter to skip): "
            read -r allowed_from

            if validate_email "$allowed_from"; then
                break
            else
                echo "Error: Invalid email address. Please try again."
            fi
        done

        # Get allowed recipients
        echo
        echo "Allowed Recipients:"
        echo "  * = Allow all recipients"
        echo "  user@example.com = Only this recipient"
        echo "  user1@example.com,user2@example.com = Multiple specific recipients"
        echo "  (empty) = No restrictions"
        echo -n "Enter allowed recipients: "
        read -r allowed_to

        # Get allowed hosts
        echo
        echo "Allowed Hosts/IPs (comma-separated, optional):"
        echo "  Example: 192.168.1.100,example.com"
        echo -n "Enter allowed hosts (or press Enter to skip): "
        read -r allowed_hosts

        # Get description
        echo
        echo -n "Description (optional, for identification): "
        read -r description

        # Confirmation
        echo
        echo "=== Summary ==="
        echo "Sender Email: ${allowed_from:-'No restrictions'}"
        echo "Allowed Recipients: ${allowed_to:-'No restrictions'}"
        echo "Allowed Hosts: ${allowed_hosts:-'No restrictions'}"
        echo "Description: ${description:-'No description'}"
        echo
        echo -n "Create this client access? [Y/n]: "
        read -r confirm

        if [[ "$confirm" =~ ^[Nn] ]]; then
            echo "Cancelled."
            exit 0
        fi

        echo
        echo "Creating client access..."
        php "$PHP_SCRIPT" "$allowed_from" "$allowed_to" "$allowed_hosts" "$description"
        ;;
    *)
        echo "Invalid choice. Exiting."
        exit 1
        ;;
esac