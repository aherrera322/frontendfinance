import {
  ArrowRight,
  Building2,
  CheckCircle,
  Globe,
  Handshake,
  MapPin,
  Plane,
  Shield,
  Star,
  TrendingUp,
  Users,
} from "lucide-react"
import Image from "next/image"
import Link from "next/link"

import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"

export default function PartnersPage() {
  return (
    <div className="min-h-screen bg-gray-200">
      {/* Navigation */}
      <nav className="border-b border-primary/20 bg-primary sticky top-0 z-50 shadow-lg">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between items-center h-16">
            <div className="flex items-center">
              <div className="flex-shrink-0 flex items-center">
                <Link href="/">
                  <Image
                    src="/images/zimple-logo.png"
                    alt="Zimple Travel Group"
                    width={160}
                    height={40}
                    className="h-10 w-auto brightness-0 invert"
                  />
                </Link>
              </div>
            </div>
            <div className="hidden md:block">
              <div className="ml-10 flex items-baseline space-x-4">
                <Link
                  href="/#services"
                  className="text-white hover:text-yellow-200 px-3 py-2 text-sm font-medium transition-colors"
                >
                  Services
                </Link>
                <Link href="/partners" className="text-yellow-200 px-3 py-2 text-sm font-medium">
                  Partners
                </Link>
                <Link
                  href="/#benefits"
                  className="text-white hover:text-yellow-200 px-3 py-2 text-sm font-medium transition-colors"
                >
                  Benefits
                </Link>
                <Link
                  href="/#contact"
                  className="text-white hover:text-yellow-200 px-3 py-2 text-sm font-medium transition-colors"
                >
                  Contact
                </Link>
              </div>
            </div>
            <div className="flex items-center space-x-4">
              <Button
                variant="outline"
                size="sm"
                className="border-white text-white hover:bg-white hover:text-primary bg-transparent"
              >
                Login
              </Button>
              <Button size="sm" className="bg-white text-primary hover:bg-gray-100">
                Apply Now
              </Button>
            </div>
          </div>
        </div>
      </nav>

      {/* Hero Section */}
      <section className="relative bg-white py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid lg:grid-cols-2 gap-8 items-center">
            <div>
              <Badge className="mb-3 bg-primary/10 text-primary border-primary/20 text-sm">
                Travel Industry Partnerships
              </Badge>
              <h1 className="text-3xl sm:text-4xl lg:text-5xl font-bold text-gray-900 mb-3 leading-tight">
                Partner with <span className="text-primary">Travel Industry</span> Leaders
              </h1>
              <p className="text-lg text-gray-600 mb-5 leading-relaxed max-w-xl">
                Join our exclusive network of travel wholesalers, agencies, airlines, hotels, and associations. Expand
                your car rental offerings and increase revenue with our comprehensive platform.
              </p>
              <div className="flex flex-col sm:flex-row gap-3 mb-6">
                <Button size="lg" className="bg-primary hover:bg-primary/90 text-base">
                  Become a Partner
                  <ArrowRight className="ml-2 h-4 w-4" />
                </Button>
                <Button variant="outline" size="lg" className="text-base bg-transparent">
                  Download Partnership Guide
                </Button>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div className="text-center">
                  <div className="text-2xl font-bold text-primary">500+</div>
                  <div className="text-sm text-gray-600">Active Partners</div>
                </div>
                <div className="text-center">
                  <div className="text-2xl font-bold text-primary">$50M+</div>
                  <div className="text-sm text-gray-600">Partner Revenue</div>
                </div>
              </div>
            </div>
            <div className="relative">
              <div className="relative z-10">
                <Image
                  src="/placeholder.svg?height=500&width=700"
                  alt="Travel industry partnership"
                  width={700}
                  height={500}
                  className="rounded-xl shadow-2xl"
                />
              </div>
              <div className="absolute -top-4 -right-4 w-64 h-64 bg-yellow-200 rounded-full mix-blend-multiply filter blur-xl opacity-60 animate-pulse"></div>
              <div className="absolute -bottom-8 -left-4 w-64 h-64 bg-primary/20 rounded-full mix-blend-multiply filter blur-xl opacity-60 animate-pulse"></div>
            </div>
          </div>
        </div>
      </section>

      {/* Partner Types Section */}
      <section className="py-8 bg-gray-100">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-6">
            <h2 className="text-2xl lg:text-3xl font-bold text-gray-900 mb-2">Partnership Opportunities</h2>
            <p className="text-lg text-gray-600 max-w-2xl mx-auto">
              We work with various types of travel industry partners to create mutually beneficial relationships
            </p>
          </div>
          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-5">
            <Card className="border-0 shadow-lg hover:shadow-xl transition-all duration-300 p-5 bg-white hover:bg-gray-50 group">
              <CardHeader className="pb-3">
                <div className="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform duration-300">
                  <Building2 className="h-6 w-6 text-white" />
                </div>
                <CardTitle className="text-xl font-semibold text-gray-900 mb-1">Travel Wholesalers</CardTitle>
                <CardDescription className="text-gray-600 leading-relaxed">
                  Expand your product portfolio with our comprehensive car rental inventory and competitive wholesale
                  rates.
                </CardDescription>
              </CardHeader>
            </Card>

            <Card className="border-0 shadow-lg hover:shadow-xl transition-all duration-300 p-5 bg-white hover:bg-gray-50 group">
              <CardHeader className="pb-3">
                <div className="w-12 h-12 bg-gradient-to-br from-green-500 to-green-600 rounded-xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform duration-300">
                  <MapPin className="h-6 w-6 text-white" />
                </div>
                <CardTitle className="text-xl font-semibold text-gray-900 mb-1">Travel Agencies</CardTitle>
                <CardDescription className="text-gray-600 leading-relaxed">
                  Offer your clients seamless car rental booking with our white-label solutions and dedicated support.
                </CardDescription>
              </CardHeader>
            </Card>

            <Card className="border-0 shadow-lg hover:shadow-xl transition-all duration-300 p-5 bg-white hover:bg-gray-50 group">
              <CardHeader className="pb-3">
                <div className="w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform duration-300">
                  <Plane className="h-6 w-6 text-white" />
                </div>
                <CardTitle className="text-xl font-semibold text-gray-900 mb-1">Airlines</CardTitle>
                <CardDescription className="text-gray-600 leading-relaxed">
                  Enhance passenger experience with integrated car rental options and earn additional ancillary revenue.
                </CardDescription>
              </CardHeader>
            </Card>

            <Card className="border-0 shadow-lg hover:shadow-xl transition-all duration-300 p-5 bg-white hover:bg-gray-50 group">
              <CardHeader className="pb-3">
                <div className="w-12 h-12 bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform duration-300">
                  <Building2 className="h-6 w-6 text-white" />
                </div>
                <CardTitle className="text-xl font-semibold text-gray-900 mb-1">Hotel Companies</CardTitle>
                <CardDescription className="text-gray-600 leading-relaxed">
                  Provide guests with convenient ground transportation options and generate additional revenue streams.
                </CardDescription>
              </CardHeader>
            </Card>

            <Card className="border-0 shadow-lg hover:shadow-xl transition-all duration-300 p-5 bg-white hover:bg-gray-50 group">
              <CardHeader className="pb-3">
                <div className="w-12 h-12 bg-gradient-to-br from-red-500 to-red-600 rounded-xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform duration-300">
                  <Users className="h-6 w-6 text-white" />
                </div>
                <CardTitle className="text-xl font-semibold text-gray-900 mb-1">Travel Associations</CardTitle>
                <CardDescription className="text-gray-600 leading-relaxed">
                  Offer exclusive member benefits and negotiated rates through our association partnership program.
                </CardDescription>
              </CardHeader>
            </Card>

            <Card className="border-0 shadow-lg hover:shadow-xl transition-all duration-300 p-5 bg-white hover:bg-gray-50 group">
              <CardHeader className="pb-3">
                <div className="w-12 h-12 bg-gradient-to-br from-primary to-primary/80 rounded-xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform duration-300">
                  <Handshake className="h-6 w-6 text-white" />
                </div>
                <CardTitle className="text-xl font-semibold text-gray-900 mb-1">Strategic Partners</CardTitle>
                <CardDescription className="text-gray-600 leading-relaxed">
                  Custom partnership solutions for unique business models and specialized travel industry needs.
                </CardDescription>
              </CardHeader>
            </Card>
          </div>
        </div>
      </section>

      {/* Partnership Benefits */}
      <section className="py-8 bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-6">
            <h2 className="text-2xl lg:text-3xl font-bold text-gray-900 mb-2">Partnership Benefits</h2>
            <p className="text-lg text-gray-600">
              Comprehensive support and benefits designed for travel industry success
            </p>
          </div>
          <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-5">
            <Card className="border-0 shadow-md hover:shadow-lg transition-shadow p-4 bg-gray-50">
              <CardContent className="text-center p-0">
                <div className="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center mx-auto mb-3">
                  <TrendingUp className="h-5 w-5 text-primary" />
                </div>
                <CardTitle className="text-lg font-semibold text-gray-900 mb-2">Revenue Growth</CardTitle>
                <CardDescription className="text-sm text-gray-600">
                  Average 25% increase in ancillary revenue within first year
                </CardDescription>
              </CardContent>
            </Card>

            <Card className="border-0 shadow-md hover:shadow-lg transition-shadow p-4 bg-gray-50">
              <CardContent className="text-center p-0">
                <div className="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center mx-auto mb-3">
                  <Shield className="h-5 w-5 text-primary" />
                </div>
                <CardTitle className="text-lg font-semibold text-gray-900 mb-2">Risk-Free</CardTitle>
                <CardDescription className="text-sm text-gray-600">
                  No setup fees, no monthly minimums, pay only for successful bookings
                </CardDescription>
              </CardContent>
            </Card>

            <Card className="border-0 shadow-md hover:shadow-lg transition-shadow p-4 bg-gray-50">
              <CardContent className="text-center p-0">
                <div className="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center mx-auto mb-3">
                  <Globe className="h-5 w-5 text-primary" />
                </div>
                <CardTitle className="text-lg font-semibold text-gray-900 mb-2">Global Reach</CardTitle>
                <CardDescription className="text-sm text-gray-600">
                  Access to inventory in 100+ countries with local support
                </CardDescription>
              </CardContent>
            </Card>

            <Card className="border-0 shadow-md hover:shadow-lg transition-shadow p-4 bg-gray-50">
              <CardContent className="text-center p-0">
                <div className="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center mx-auto mb-3">
                  <Users className="h-5 w-5 text-primary" />
                </div>
                <CardTitle className="text-lg font-semibold text-gray-900 mb-2">Dedicated Support</CardTitle>
                <CardDescription className="text-sm text-gray-600">
                  Personal account manager and 24/7 technical support
                </CardDescription>
              </CardContent>
            </Card>
          </div>
        </div>
      </section>

      {/* Partnership Tiers */}
      <section className="py-8 bg-gray-100">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-6">
            <h2 className="text-2xl lg:text-3xl font-bold text-gray-900 mb-2">Partnership Tiers</h2>
            <p className="text-lg text-gray-600">Choose the partnership level that best fits your business needs</p>
          </div>
          <div className="grid md:grid-cols-3 gap-6">
            <Card className="border-0 shadow-lg bg-white p-6">
              <CardHeader className="text-center pb-4">
                <Badge className="mx-auto mb-3 bg-gray-100 text-gray-700">Essential</Badge>
                <CardTitle className="text-2xl font-bold text-gray-900">Basic Partner</CardTitle>
                <CardDescription className="text-gray-600">Perfect for getting started</CardDescription>
              </CardHeader>
              <CardContent className="space-y-3">
                <div className="flex items-center text-sm">
                  <CheckCircle className="h-4 w-4 text-green-500 mr-2" />
                  <span>API access to car rental inventory</span>
                </div>
                <div className="flex items-center text-sm">
                  <CheckCircle className="h-4 w-4 text-green-500 mr-2" />
                  <span>Standard commission rates</span>
                </div>
                <div className="flex items-center text-sm">
                  <CheckCircle className="h-4 w-4 text-green-500 mr-2" />
                  <span>Email support</span>
                </div>
                <div className="flex items-center text-sm">
                  <CheckCircle className="h-4 w-4 text-green-500 mr-2" />
                  <span>Basic reporting dashboard</span>
                </div>
                <div className="pt-4">
                  <Button className="w-full bg-gray-600 hover:bg-gray-700">Apply Now</Button>
                </div>
              </CardContent>
            </Card>

            <Card className="border-2 border-primary shadow-xl bg-white p-6 relative">
              <div className="absolute -top-3 left-1/2 transform -translate-x-1/2">
                <Badge className="bg-primary text-white">Most Popular</Badge>
              </div>
              <CardHeader className="text-center pb-4">
                <Badge className="mx-auto mb-3 bg-primary/10 text-primary">Professional</Badge>
                <CardTitle className="text-2xl font-bold text-gray-900">Premium Partner</CardTitle>
                <CardDescription className="text-gray-600">For established travel businesses</CardDescription>
              </CardHeader>
              <CardContent className="space-y-3">
                <div className="flex items-center text-sm">
                  <CheckCircle className="h-4 w-4 text-green-500 mr-2" />
                  <span>Everything in Basic Partner</span>
                </div>
                <div className="flex items-center text-sm">
                  <CheckCircle className="h-4 w-4 text-green-500 mr-2" />
                  <span>Enhanced commission rates</span>
                </div>
                <div className="flex items-center text-sm">
                  <CheckCircle className="h-4 w-4 text-green-500 mr-2" />
                  <span>Priority phone support</span>
                </div>
                <div className="flex items-center text-sm">
                  <CheckCircle className="h-4 w-4 text-green-500 mr-2" />
                  <span>White-label booking engine</span>
                </div>
                <div className="flex items-center text-sm">
                  <CheckCircle className="h-4 w-4 text-green-500 mr-2" />
                  <span>Advanced analytics</span>
                </div>
                <div className="pt-4">
                  <Button className="w-full bg-primary hover:bg-primary/90">Apply Now</Button>
                </div>
              </CardContent>
            </Card>

            <Card className="border-0 shadow-lg bg-white p-6">
              <CardHeader className="text-center pb-4">
                <Badge className="mx-auto mb-3 bg-yellow-100 text-yellow-700">Enterprise</Badge>
                <CardTitle className="text-2xl font-bold text-gray-900">Strategic Partner</CardTitle>
                <CardDescription className="text-gray-600">For large-scale operations</CardDescription>
              </CardHeader>
              <CardContent className="space-y-3">
                <div className="flex items-center text-sm">
                  <CheckCircle className="h-4 w-4 text-green-500 mr-2" />
                  <span>Everything in Premium Partner</span>
                </div>
                <div className="flex items-center text-sm">
                  <CheckCircle className="h-4 w-4 text-green-500 mr-2" />
                  <span>Maximum commission rates</span>
                </div>
                <div className="flex items-center text-sm">
                  <CheckCircle className="h-4 w-4 text-green-500 mr-2" />
                  <span>Dedicated account manager</span>
                </div>
                <div className="flex items-center text-sm">
                  <CheckCircle className="h-4 w-4 text-green-500 mr-2" />
                  <span>Custom integration support</span>
                </div>
                <div className="flex items-center text-sm">
                  <CheckCircle className="h-4 w-4 text-green-500 mr-2" />
                  <span>Co-marketing opportunities</span>
                </div>
                <div className="pt-4">
                  <Button className="w-full bg-yellow-600 hover:bg-yellow-700">Contact Sales</Button>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </section>

      {/* Success Stories */}
      <section className="py-8 bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-6">
            <h2 className="text-2xl lg:text-3xl font-bold text-gray-900 mb-2">Partner Success Stories</h2>
            <p className="text-lg text-gray-600">
              See how our partners are growing their business with Zimple Travel Group
            </p>
          </div>
          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-5">
            <Card className="border-0 shadow-lg bg-gray-50 p-5">
              <CardContent className="p-0">
                <div className="flex items-center mb-3">
                  <div className="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                    <Building2 className="h-5 w-5 text-blue-600" />
                  </div>
                  <div>
                    <CardTitle className="text-lg font-semibold">Global Travel Solutions</CardTitle>
                    <CardDescription className="text-sm text-gray-600">Travel Wholesaler</CardDescription>
                  </div>
                </div>
                <p className="text-sm text-gray-700 mb-3">
                  "Partnering with Zimple increased our car rental revenue by 45% in the first year. Their API
                  integration was seamless."
                </p>
                <div className="flex items-center text-xs text-gray-500">
                  <Star className="h-3 w-3 text-yellow-400 mr-1" />
                  <span>45% revenue increase</span>
                </div>
              </CardContent>
            </Card>

            <Card className="border-0 shadow-lg bg-gray-50 p-5">
              <CardContent className="p-0">
                <div className="flex items-center mb-3">
                  <div className="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-3">
                    <MapPin className="h-5 w-5 text-green-600" />
                  </div>
                  <div>
                    <CardTitle className="text-lg font-semibold">Adventure Travel Co.</CardTitle>
                    <CardDescription className="text-sm text-gray-600">Travel Agency</CardDescription>
                  </div>
                </div>
                <p className="text-sm text-gray-700 mb-3">
                  "The white-label solution allowed us to offer car rentals under our brand. Customer satisfaction
                  improved significantly."
                </p>
                <div className="flex items-center text-xs text-gray-500">
                  <Star className="h-3 w-3 text-yellow-400 mr-1" />
                  <span>95% customer satisfaction</span>
                </div>
              </CardContent>
            </Card>

            <Card className="border-0 shadow-lg bg-gray-50 p-5">
              <CardContent className="p-0">
                <div className="flex items-center mb-3">
                  <div className="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center mr-3">
                    <Plane className="h-5 w-5 text-purple-600" />
                  </div>
                  <div>
                    <CardTitle className="text-lg font-semibold">SkyLine Airways</CardTitle>
                    <CardDescription className="text-sm text-gray-600">Regional Airline</CardDescription>
                  </div>
                </div>
                <p className="text-sm text-gray-700 mb-3">
                  "Integrating car rentals into our booking flow generated $2M in additional ancillary revenue last
                  year."
                </p>
                <div className="flex items-center text-xs text-gray-500">
                  <Star className="h-3 w-3 text-yellow-400 mr-1" />
                  <span>$2M ancillary revenue</span>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </section>

      {/* CTA Section */}
      <section className="py-8 bg-gradient-to-r from-primary to-primary/80">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
          <h2 className="text-2xl lg:text-3xl font-bold text-white mb-3">Ready to Partner with Us?</h2>
          <p className="text-lg text-white/90 mb-5">
            Join hundreds of travel industry leaders who trust Zimple Travel Group for their car rental needs.
          </p>
          <div className="flex flex-col sm:flex-row gap-3 justify-center">
            <Button size="lg" variant="secondary" className="bg-white text-primary hover:bg-gray-100">
              <Handshake className="mr-2 h-4 w-4" />
              Apply for Partnership
            </Button>
            <Button size="lg" variant="outline" className="border-white text-white hover:bg-white/10 bg-transparent">
              Schedule Consultation
            </Button>
          </div>
          <div className="mt-5 text-white/80">
            <p className="text-sm">
              Questions about partnerships? Contact us at{" "}
              <a href="mailto:partnerships@zimpletravel.com" className="text-yellow-200 hover:underline">
                partnerships@zimpletravel.com
              </a>
            </p>
          </div>
        </div>
      </section>

      {/* Footer */}
      <footer className="bg-gray-800 text-white py-6">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid md:grid-cols-4 gap-6">
            <div>
              <div className="flex items-center mb-3">
                <Image
                  src="/images/zimple-logo.png"
                  alt="Zimple Travel Group"
                  width={140}
                  height={35}
                  className="h-8 w-auto brightness-0 invert"
                />
              </div>
              <p className="text-gray-400 text-sm">Your trusted partner for global car rental solutions.</p>
            </div>
            <div>
              <h3 className="font-semibold mb-3 text-base">Partnership</h3>
              <ul className="space-y-1 text-gray-400 text-sm">
                <li>
                  <Link href="#" className="hover:text-white transition-colors">
                    Become a Partner
                  </Link>
                </li>
                <li>
                  <Link href="#" className="hover:text-white transition-colors">
                    Partnership Tiers
                  </Link>
                </li>
                <li>
                  <Link href="#" className="hover:text-white transition-colors">
                    API Documentation
                  </Link>
                </li>
                <li>
                  <Link href="#" className="hover:text-white transition-colors">
                    Partner Portal
                  </Link>
                </li>
              </ul>
            </div>
            <div>
              <h3 className="font-semibold mb-3 text-base">Support</h3>
              <ul className="space-y-1 text-gray-400 text-sm">
                <li>
                  <Link href="#" className="hover:text-white transition-colors">
                    Help Center
                  </Link>
                </li>
                <li>
                  <Link href="#" className="hover:text-white transition-colors">
                    Technical Support
                  </Link>
                </li>
                <li>
                  <Link href="#" className="hover:text-white transition-colors">
                    Training Resources
                  </Link>
                </li>
                <li>
                  <Link href="#" className="hover:text-white transition-colors">
                    Contact Support
                  </Link>
                </li>
              </ul>
            </div>
            <div>
              <h3 className="font-semibold mb-3 text-base">Resources</h3>
              <ul className="space-y-1 text-gray-400 text-sm">
                <li>
                  <Link href="#" className="hover:text-white transition-colors">
                    Partnership Guide
                  </Link>
                </li>
                <li>
                  <Link href="#" className="hover:text-white transition-colors">
                    Case Studies
                  </Link>
                </li>
                <li>
                  <Link href="#" className="hover:text-white transition-colors">
                    Marketing Materials
                  </Link>
                </li>
                <li>
                  <Link href="#" className="hover:text-white transition-colors">
                    Industry Reports
                  </Link>
                </li>
              </ul>
            </div>
          </div>
          <div className="border-t border-gray-700 mt-4 pt-4 text-center text-gray-400">
            <p className="text-sm">&copy; 2024 Zimple Travel Group. All rights reserved.</p>
          </div>
        </div>
      </footer>
    </div>
  )
}
