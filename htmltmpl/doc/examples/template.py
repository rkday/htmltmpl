
from htmltmpl import Template
tmpl = Template(template = "template.tmpl",
                template_path = ["."])

# Set the title.
tmpl["title"] = "Our customers"

# Create the 'Customers' loop.
customers = []

# First customer.
customer = {}
customer["name"] = "Joe Sixpack"
customer["city"] = "Los Angeles"
customer["new"] = 0
customers.append(customer)

# Second customer.
customer = {}
customer["name"] = "Paul Newman"
customer["city"] = "New York"
customer["new"] = 1
customers.append(customer)

tmpl["Customers"] = customers

# Print the processed template.
print tmpl.output()
